<?php

namespace App\Dns;

use App\Models\Setting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * The public IP addresses of this server, which domains must point at. They
 * are set by the user or detected on request; nothing is looked up silently.
 */
class ServerAddress
{
    private const V4 = 'server.ipv4';

    private const V6 = 'server.ipv6';

    public function ipv4(): ?string
    {
        return Setting::read(self::V4) ?: null;
    }

    public function ipv6(): ?string
    {
        return Setting::read(self::V6) ?: null;
    }

    public function isConfigured(): bool
    {
        return $this->ipv4() !== null || $this->ipv6() !== null;
    }

    public function save(?string $ipv4, ?string $ipv6): void
    {
        Setting::write(self::V4, $ipv4 === '' ? null : $ipv4);
        Setting::write(self::V6, $ipv6 === '' ? null : $ipv6);
    }

    /**
     * Ask an external service (api.ipify.org) which addresses this server
     * connects from. Only called when the user presses "Detect".
     *
     * @return array{ipv4: string|null, ipv6: string|null}
     */
    public function detect(): array
    {
        return [
            'ipv4' => $this->fetch('https://api.ipify.org', FILTER_FLAG_IPV4),
            'ipv6' => $this->fetch('https://api6.ipify.org', FILTER_FLAG_IPV6),
        ];
    }

    private function fetch(string $url, int $family): ?string
    {
        try {
            $ip = trim(Http::timeout(5)->get($url)->body());
        } catch (ConnectionException) {
            return null;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, $family) === false ? null : $ip;
    }
}
