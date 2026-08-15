<?php

namespace App\Support;

final class UkPostcode
{
    public const FORMAT_REGEX = '/^[A-Z]{1,2}\d[A-Z\d]?\d[A-Z]{2}$/';

    /**
     * Trim, strip internal whitespace and uppercase. Safe on null.
     * e.g. " ss0 9xx " -> "SS09XX"
     */
    public static function normalizeForInput(?string $raw): string
    {
        return strtoupper(str_replace(' ', '', trim((string) $raw)));
    }

    /**
     * True if the space-stripped, uppercased form matches the UK postcode pattern.
     */
    public static function isValidFormat(string $normalized): bool
    {
        return (bool) preg_match(self::FORMAT_REGEX, $normalized);
    }

    /**
     * Re-insert the canonical single space before the 3-character inward code.
     * e.g. "SS09XX" -> "SS0 9XX"
     */
    public static function format(string $normalized): string
    {
        return (string) preg_replace('/^(.*)(\d[A-Z]{2})$/', '$1 $2', $normalized);
    }
}
