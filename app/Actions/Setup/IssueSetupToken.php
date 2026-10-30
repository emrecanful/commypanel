<?php

namespace App\Actions\Setup;

use App\Models\SetupToken;
use Illuminate\Support\Str;

class IssueSetupToken
{
    public const LIFETIME_MINUTES = 60;

    /**
     * Create a single-use token, invalidating any earlier ones. Only a hash
     * is stored, so the plain token can be shown exactly once.
     */
    public function __invoke(): string
    {
        SetupToken::query()->whereNull('used_at')->delete();

        $token = Str::random(48);

        SetupToken::query()->create([
            'token_hash' => SetupToken::hash($token),
            'expires_at' => now()->addMinutes(self::LIFETIME_MINUTES),
        ]);

        return $token;
    }
}
