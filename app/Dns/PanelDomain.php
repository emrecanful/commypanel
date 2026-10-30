<?php

namespace App\Dns;

use App\Models\Setting;

/**
 * The domain the panel itself is served on (set during first-run setup).
 * No app may use it.
 */
class PanelDomain
{
    private const KEY = 'panel.domain';

    public function name(): ?string
    {
        return Setting::read(self::KEY) ?: null;
    }

    public function save(?string $name): void
    {
        Setting::write(self::KEY, $name === '' ? null : $name);
    }

    public function isTaken(string $name): bool
    {
        return $this->name() === $name;
    }
}
