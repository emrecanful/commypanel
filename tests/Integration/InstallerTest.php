<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * The installer needs root and a real Debian/Ubuntu to do its work, so these
 * tests run it in --render-only mode: it writes every config file below a
 * scratch root and touches nothing else. They check the files it would put
 * on a server, and that the scripts are valid shell.
 */
beforeEach(function () {
    if (trim((string) shell_exec('command -v bash 2>/dev/null')) === '') {
        $this->markTestSkipped('bash is not available.');
    }

    $this->root = storage_path('framework/testing/installer-'.bin2hex(random_bytes(4)));
    File::ensureDirectoryExists($this->root);

    $this->setup = function (array $args = [], array $env = []) {
        return Process::env([
            'COMMYPANEL_ROOT' => $this->root,
            'COMMYPANEL_ADDRESS' => '203.0.113.7',
            ...$env,
        ])->run(['bash', base_path('installer/setup.sh'), '--render-only', ...$args]);
    };

    $this->read = fn (string $path) => file_get_contents($this->root.$path);
});

afterEach(fn () => File::deleteDirectory($this->root));

it('ships valid shell scripts', function (string $script) {
    $result = Process::run(['bash', '-n', base_path($script)]);

    expect($result->successful())->toBeTrue($result->errorOutput());
})->with([
    'installer/install.sh',
    'installer/setup.sh',
    'installer/templates/commypanel-cli',
    'scripts/build-release.sh',
]);

it('renders every config file with no placeholder left over', function () {
    $result = ($this->setup)();

    expect($result->successful())->toBeTrue($result->errorOutput().$result->output());

    $files = [
        '/etc/commypanel/env',
        '/etc/commypanel/install.conf',
        '/etc/nginx/conf.d/00-commypanel.conf',
        '/etc/nginx/conf.d/10-commypanel-panel.conf',
        '/etc/php/8.4/fpm/pool.d/commypanel.conf',
        '/etc/systemd/system/commypanel-agent.service',
        '/etc/systemd/system/commypanel-queue@.service',
        '/etc/systemd/system/commypanel-scheduler.service',
        '/usr/local/bin/commypanel',
    ];

    foreach ($files as $file) {
        expect($this->root.$file)->toBeFile();
        expect(($this->read)($file))->not->toContain('@@');
    }
});

it('gives the agent only the panel user as caller and runs the panel as that user', function () {
    ($this->setup)([], ['COMMYPANEL_PANEL_UID' => '1234']);

    $agent = ($this->read)('/etc/systemd/system/commypanel-agent.service');
    expect($agent)->toContain('--allow-uid 1234')
        ->toContain('--socket /run/commypanel/agent.sock')
        ->toContain('--socket-group commypanel')
        ->toContain('--data-dir /var/lib/commypanel')
        ->toContain('--nginx-sites-dir /etc/nginx/commypanel')
        ->toContain('NoNewPrivileges=yes')
        ->not->toContain('User=');

    foreach (['commypanel-queue@.service', 'commypanel-scheduler.service'] as $unit) {
        $text = ($this->read)("/etc/systemd/system/{$unit}");
        expect($text)->toContain('User=commypanel')
            ->toContain('Group=commypanel')
            ->toContain('ProtectSystem=full')
            ->toContain('ReadWritePaths=/var/lib/commypanel')
            ->toContain('Environment=LARAVEL_STORAGE_PATH=/var/lib/commypanel/storage');
    }

    expect(($this->read)('/etc/systemd/system/commypanel-queue@.service'))
        ->toContain('/usr/bin/php artisan queue:work');
    expect(($this->read)('/etc/systemd/system/commypanel-scheduler.service'))
        ->toContain('/usr/bin/php artisan schedule:work');
});

it('configures production settings and the agent driver', function () {
    ($this->setup)();

    $env = ($this->read)('/etc/commypanel/env');

    expect($env)->toContain('APP_ENV=production')
        ->toContain('APP_DEBUG=false')
        ->toContain('APP_URL=https://203.0.113.7:8443')
        ->toContain('HOST_DRIVER=agent')
        ->toContain('AGENT_SOCKET=/run/commypanel/agent.sock')
        ->toContain('COMMYPANEL_DATA_PATH=/var/lib/commypanel')
        ->toContain('LARAVEL_STORAGE_PATH=/var/lib/commypanel/storage')
        ->toContain('SESSION_SECURE_COOKIE=true')
        ->toContain('ACME_STAGING=false');
    expect($env)->toMatch('/^APP_KEY=base64:[A-Za-z0-9+\/]{43}=$/m');
    expect(decoct(fileperms($this->root.'/etc/commypanel/env') & 0777))->toBe('640');
});

it('serves the panel over TLS and only ever runs index.php', function () {
    ($this->setup)();

    $vhost = ($this->read)('/etc/nginx/conf.d/10-commypanel-panel.conf');

    expect($vhost)->toContain('listen 8443 ssl;')
        ->toContain('root /opt/commypanel/current/public;')
        ->toContain('ssl_certificate /etc/commypanel/tls/panel.crt;')
        ->toContain('fastcgi_pass unix:/run/php/commypanel.sock;')
        ->toContain('$realpath_root')
        ->toContain('location ~ ^/index\.php(/|$)')
        ->toContain('return 404;')
        ->toContain('deny all;')
        ->not->toContain('http2');

    expect(($this->read)('/etc/nginx/conf.d/00-commypanel.conf'))->toContain('include /etc/nginx/commypanel/*.conf;');
});

it('gives the panel its own PHP-FPM pool owned by the panel user', function () {
    ($this->setup)([], ['COMMYPANEL_PHP_VERSION' => '8.5']);

    $pool = ($this->read)('/etc/php/8.5/fpm/pool.d/commypanel.conf');

    expect($pool)->toContain('[commypanel]')
        ->toContain('user = commypanel')
        ->toContain('listen = /run/php/commypanel.sock')
        ->toContain('listen.owner = www-data')
        ->toContain('listen.mode = 0660')
        ->toContain('env[LARAVEL_STORAGE_PATH] = /var/lib/commypanel/storage')
        ->toContain('expose_php');
});

it('honours a custom panel port everywhere', function () {
    ($this->setup)(['--panel-port', '9443']);

    expect(($this->read)('/etc/nginx/conf.d/10-commypanel-panel.conf'))->toContain('listen 9443 ssl;')
        ->and(($this->read)('/etc/commypanel/env'))->toContain('APP_URL=https://203.0.113.7:9443')
        ->and(($this->read)('/etc/commypanel/install.conf'))->toContain('PANEL_PORT=9443');
});

it('is safe to run again: the key, port and address survive an upgrade', function () {
    ($this->setup)(['--panel-port', '9443']);
    $before = ($this->read)('/etc/commypanel/env');

    $second = ($this->setup)([], ['COMMYPANEL_ADDRESS' => '198.51.100.99']);

    expect($second->successful())->toBeTrue($second->errorOutput())
        ->and($second->output())->toContain('Upgrading')
        ->and(($this->read)('/etc/commypanel/env'))->toBe($before)
        ->and(($this->read)('/etc/commypanel/install.conf'))->toContain('PANEL_PORT=9443')->toContain('SERVER_ADDR=203.0.113.7')
        ->and(($this->read)('/etc/nginx/conf.d/10-commypanel-panel.conf'))->toContain('listen 9443 ssl;');
});

it('adds settings that a newer release needs without touching existing values', function () {
    ($this->setup)();
    $path = $this->root.'/etc/commypanel/env';
    file_put_contents($path, preg_replace('/^HOST_DRIVER=.*\n/m', '', (string) file_get_contents($path)).'CUSTOM_SETTING=keep-me'."\n");

    ($this->setup)();

    $env = ($this->read)('/etc/commypanel/env');
    expect($env)->toContain('HOST_DRIVER=agent')->toContain('CUSTOM_SETTING=keep-me');
});

it('refuses to render outside a scratch root and rejects bad ports and options', function () {
    $noRoot = Process::run(['bash', base_path('installer/setup.sh'), '--render-only']);
    expect($noRoot->failed())->toBeTrue()->and($noRoot->errorOutput())->toContain('COMMYPANEL_ROOT');

    foreach (['80', '8080', '70000', 'abc', '1023'] as $port) {
        $result = ($this->setup)(['--panel-port', $port]);
        expect($result->failed())->toBeTrue("port {$port} should be rejected");
    }

    $unknown = ($this->setup)(['--bogus']);
    expect($unknown->failed())->toBeTrue();
});

it('installs a command line tool that offers the documented commands', function () {
    ($this->setup)();

    $cli = ($this->read)('/usr/local/bin/commypanel');

    foreach (['setup-url', 'admin:reset', 'status', 'logs', 'upgrade', 'version', 'artisan'] as $command) {
        expect($cli)->toContain("{$command})");
    }
    expect($cli)->toContain('Run this as root')
        ->and(decoct(fileperms($this->root.'/usr/local/bin/commypanel') & 0777))->toBe('755');
});

it('makes the bootstrap installer verify checksums before unpacking', function () {
    $installer = file_get_contents(base_path('installer/install.sh'));

    expect($installer)->toContain('sha256sum --check --status')
        ->toContain("--proto '=https'")
        ->toContain('--no-same-owner')
        ->and(strpos($installer, 'sha256sum --check'))->toBeLessThan(strpos($installer, 'tar -xzf'));
});

it('never lets the panel user write to the release code', function () {
    $setup = file_get_contents(base_path('installer/setup.sh'));

    expect($setup)->toContain('chown -R root:root "$RELEASE_DIR"')
        ->toContain('chmod -R go-w "$RELEASE_DIR"')
        ->toContain('bootstrap-cache/$VERSION');
});
