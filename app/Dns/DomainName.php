<?php

namespace App\Dns;

final class DomainName
{
    /**
     * Normalise user input to a lowercase ASCII hostname, or null when it is
     * not a plain domain name (IP addresses, wildcards, URLs and single labels
     * are all refused).
     */
    public static function normalize(string $input): ?string
    {
        $name = rtrim(strtolower(trim($input)), '.');

        if (preg_match('/[^\x20-\x7e]/', $name) === 1 && function_exists('idn_to_ascii')) {
            $ascii = idn_to_ascii($name, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            $name = $ascii === false ? '' : $ascii;
        }

        if ($name === '' || strlen($name) > 253 || filter_var($name, FILTER_VALIDATE_IP) !== false) {
            return null;
        }

        $labels = explode('.', $name);

        if (count($labels) < 2) {
            return null;
        }

        foreach ($labels as $label) {
            if (preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $label) !== 1) {
                return null;
            }
        }

        // A purely numeric top-level label would make this look like an IP address.
        return ctype_digit(end($labels)) ? null : $name;
    }
}
