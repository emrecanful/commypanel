<?php

use App\Dns\Cidr;
use App\Dns\Cloudflare;
use App\Dns\DomainName;

describe('domain names', function () {
    it('normalises valid input', function (string $input, string $expected) {
        expect(DomainName::normalize($input))->toBe($expected);
    })->with([
        ['Example.COM', 'example.com'],
        ['  app.example.com.  ', 'app.example.com'],
        ['my-app.sub.example.co.uk', 'my-app.sub.example.co.uk'],
        ['bücher.example', 'xn--bcher-kva.example'],
        ['xn--bcher-kva.example', 'xn--bcher-kva.example'],
    ]);

    it('rejects anything that is not a plain domain', function (string $input) {
        expect(DomainName::normalize($input))->toBeNull();
    })->with([
        'empty' => '',
        'single label' => 'localhost',
        'ip v4' => '203.0.113.7',
        'ip v6' => '2001:db8::1',
        'url' => 'https://example.com',
        'path' => 'example.com/admin',
        'wildcard' => '*.example.com',
        'spaces' => 'exa mple.com',
        'underscore' => 'my_app.example.com',
        'leading dash' => '-a.example.com',
        'double dot' => 'a..example.com',
        'semicolon' => 'example.com;',
        'newline' => "example.com\nserver_name evil",
        'numeric tld' => 'example.123',
        'too long label' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.example.com',
    ]);
});

describe('cidr', function () {
    it('matches addresses inside a range', function (string $cidr, string $ip, bool $expected) {
        expect(Cidr::contains($cidr, $ip))->toBe($expected);
    })->with([
        ['104.16.0.0/13', '104.16.0.1', true],
        ['104.16.0.0/13', '104.23.255.255', true],
        ['104.16.0.0/13', '104.24.0.0', false],
        ['10.0.0.0/8', '10.255.0.1', true],
        ['10.0.0.0/8', '11.0.0.1', false],
        ['203.0.113.7/32', '203.0.113.7', true],
        ['203.0.113.7/32', '203.0.113.8', false],
        ['2606:4700::/32', '2606:4700:3030::1', true],
        ['2606:4700::/32', '2606:4701::1', false],
        ['10.0.0.0/8', '2606:4700::1', false],
        ['10.0.0.0/8', 'not-an-ip', false],
    ]);

    it('recognises Cloudflare addresses', function () {
        expect(Cloudflare::owns('104.21.5.9'))->toBeTrue()
            ->and(Cloudflare::owns('172.67.10.10'))->toBeTrue()
            ->and(Cloudflare::owns('2606:4700:3037::6815:509'))->toBeTrue()
            ->and(Cloudflare::owns('203.0.113.7'))->toBeFalse()
            ->and(Cloudflare::owns('2001:db8::1'))->toBeFalse();
    });
});
