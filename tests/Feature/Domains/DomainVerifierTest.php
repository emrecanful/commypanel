<?php

use App\Dns\DnsResolver;
use App\Dns\DomainVerifier;
use App\Dns\ServerAddress;
use App\Enums\DomainStatus;
use Tests\Support\FakeDnsResolver;

function verifier(array $records, ?string $v4 = '203.0.113.7', ?string $v6 = null): DomainVerifier
{
    app(ServerAddress::class)->save($v4, $v6);
    app()->instance(DnsResolver::class, new FakeDnsResolver($records));

    return app(DomainVerifier::class);
}

it('verifies a domain whose A record points at the server', function () {
    $check = verifier(['app.example.com' => ['a' => ['203.0.113.7']]])->check('app.example.com');

    expect($check->status)->toBe(DomainStatus::Verified)
        ->and($check->problems)->toBe([])
        ->and($check->a)->toBe(['203.0.113.7']);
});

it('asks for the server address first', function () {
    $check = verifier([], null, null)->check('app.example.com');

    expect($check->status)->toBe(DomainStatus::Pending)
        ->and($check->problems[0])->toContain('Set this server');
});

it('stays pending while no record exists', function () {
    $check = verifier([])->check('app.example.com');

    expect($check->status)->toBe(DomainStatus::Pending)
        ->and($check->problems[0])->toContain('No A or AAAA record');
});

it('reports an A record that points somewhere else', function () {
    $check = verifier(['app.example.com' => ['a' => ['198.51.100.9']]])->check('app.example.com');

    expect($check->status)->toBe(DomainStatus::Misconfigured)
        ->and($check->problems[0])->toContain('198.51.100.9')->toContain('203.0.113.7');
});

it('accepts several A records as long as one is the server', function () {
    $check = verifier(['app.example.com' => ['a' => ['198.51.100.9', '203.0.113.7']]])->check('app.example.com');

    expect($check->status)->toBe(DomainStatus::Verified);
});

it('follows CNAME records', function () {
    $check = verifier([
        'www.example.com' => ['cname' => 'app.example.com.'],
        'app.example.com' => ['cname' => 'host.example.net'],
        'host.example.net' => ['a' => ['203.0.113.7']],
    ])->check('www.example.com');

    expect($check->status)->toBe(DomainStatus::Verified);
});

it('does not loop forever on a CNAME cycle', function () {
    $check = verifier([
        'a.example.com' => ['cname' => 'b.example.com'],
        'b.example.com' => ['cname' => 'a.example.com'],
    ])->check('a.example.com');

    expect($check->status)->toBe(DomainStatus::Pending);
});

it('flags an AAAA record when the server has no IPv6 address', function () {
    $check = verifier(['app.example.com' => ['a' => ['203.0.113.7'], 'aaaa' => ['2001:db8::99']]])->check('app.example.com');

    expect($check->status)->toBe(DomainStatus::Misconfigured)
        ->and($check->problems[0])->toContain('AAAA record exists')->toContain('no IPv6');
});

it('verifies both address families when the server has both', function () {
    $records = ['app.example.com' => ['a' => ['203.0.113.7'], 'aaaa' => ['2001:db8::7']]];

    expect(verifier($records, '203.0.113.7', '2001:db8::7')->check('app.example.com')->status)->toBe(DomainStatus::Verified);

    $wrong = ['app.example.com' => ['a' => ['203.0.113.7'], 'aaaa' => ['2001:db8::99']]];
    $check = verifier($wrong, '203.0.113.7', '2001:db8::7')->check('app.example.com');

    expect($check->status)->toBe(DomainStatus::Misconfigured)
        ->and($check->problems[0])->toContain('2001:db8::99')->toContain('2001:db8::7');
});

it('supports an IPv6-only server', function () {
    $check = verifier(['app.example.com' => ['aaaa' => ['2001:db8::7']]], null, '2001:db8::7')->check('app.example.com');

    expect($check->status)->toBe(DomainStatus::Verified);

    $missing = verifier(['app.example.com' => ['a' => ['198.51.100.9']]], null, '2001:db8::7')->check('app.example.com');
    expect($missing->status)->toBe(DomainStatus::Misconfigured)
        ->and($missing->problems[0])->toContain('Add an AAAA record');
});

it('recognises a Cloudflare proxy and explains it', function () {
    $check = verifier(['app.example.com' => ['a' => ['104.21.5.9', '172.67.10.10'], 'aaaa' => ['2606:4700:3037::6815:509']]])->check('app.example.com');

    expect($check->status)->toBe(DomainStatus::Proxied)
        ->and($check->problems[0])->toContain('DNS only');
});
