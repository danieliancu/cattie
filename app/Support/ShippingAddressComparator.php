<?php

namespace App\Support;

final class ShippingAddressComparator
{
    private const KEYS = ['address_line_1', 'address_line_2', 'city', 'county', 'postcode'];

    /**
     * Compares only the delivery-address fields (never recipient name, never country,
     * which is always GB). Case/whitespace-insensitive. Missing data on either side
     * never counts as a match.
     */
    public static function matches(?array $a, ?array $b): bool
    {
        if (! $a || ! $b) {
            return false;
        }

        foreach (self::KEYS as $key) {
            if (self::fold($a[$key] ?? null) !== self::fold($b[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private static function fold(?string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $value)));
    }
}
