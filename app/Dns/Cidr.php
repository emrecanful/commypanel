<?php

namespace App\Dns;

final class Cidr
{
    /**
     * Whether an IPv4 or IPv6 address lies inside a CIDR range.
     */
    public static function contains(string $cidr, string $ip): bool
    {
        [$subnet, $bits] = array_pad(explode('/', $cidr, 2), 2, null);

        $ipBytes = @inet_pton($ip);
        $subnetBytes = @inet_pton((string) $subnet);

        if ($ipBytes === false || $subnetBytes === false || strlen($ipBytes) !== strlen($subnetBytes)) {
            return false;
        }

        $bits = (int) ($bits ?? strlen($ipBytes) * 8);
        $fullBytes = intdiv($bits, 8);

        if (substr($ipBytes, 0, $fullBytes) !== substr($subnetBytes, 0, $fullBytes)) {
            return false;
        }

        $remaining = $bits % 8;

        if ($remaining === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remaining)) & 0xFF;

        return (ord($ipBytes[$fullBytes]) & $mask) === (ord($subnetBytes[$fullBytes]) & $mask);
    }

    /**
     * @param  list<string>  $ranges
     */
    public static function containsAny(array $ranges, string $ip): bool
    {
        foreach ($ranges as $range) {
            if (self::contains($range, $ip)) {
                return true;
            }
        }

        return false;
    }
}
