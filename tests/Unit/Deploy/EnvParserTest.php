<?php

use App\Deploy\EnvParser;

it('parses variables the way .env files are written', function () {
    $result = (new EnvParser)->parse("# c\n\nA=1\nexport B='two words'\nC=\"quoted # not a comment\"\nD=plain # comment\nE=a=b\nEMPTY=\n");

    expect($result['vars'])->toBe(['A' => '1', 'B' => 'two words', 'C' => 'quoted # not a comment', 'D' => 'plain', 'E' => 'a=b', 'EMPTY' => ''])
        ->and($result['skipped'])->toBe([]);
});

it('skips PORT, invalid names and lines without a value', function () {
    $result = (new EnvParser)->parse("PORT=80\n1BAD=x\nno equals\nbad-key=1\nOK=1");

    expect($result['vars'])->toBe(['OK' => '1'])->and($result['skipped'])->toHaveCount(4);
});

it('handles Windows line endings and caps the number of variables', function () {
    expect((new EnvParser)->parse("A=1\r\nB=2")['vars'])->toBe(['A' => '1', 'B' => '2']);

    $many = implode("\n", array_map(fn ($i) => "K{$i}=v", range(1, 250)));
    expect((new EnvParser)->parse($many)['vars'])->toHaveCount(EnvParser::MAX_VARS);
});
