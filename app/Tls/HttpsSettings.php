<?php

namespace App\Tls;

use App\Models\Setting;

/**
 * Whether automatic HTTPS is switched on. Let's Encrypt requires the user to
 * accept its subscriber agreement, so nothing is requested until they do.
 */
class HttpsSettings
{
    private const AGREED_AT = 'acme.tos_agreed_at';

    private const EMAIL = 'acme.email';

    public function enabled(): bool
    {
        return Setting::read(self::AGREED_AT) !== null;
    }

    public function email(): ?string
    {
        return Setting::read(self::EMAIL) ?: null;
    }

    public function staging(): bool
    {
        return (bool) config('commypanel.https.staging');
    }

    public function enable(?string $email): void
    {
        Setting::write(self::AGREED_AT, now()->toIso8601String());
        Setting::write(self::EMAIL, $email === '' ? null : $email);
    }

    public function disable(): void
    {
        Setting::write(self::AGREED_AT, null);
    }
}
