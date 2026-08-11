<?php

namespace App\Support;

final class Money
{
    public static function format(int $minor, string $currency = 'GBP'): string
    {
        $major = intdiv($minor, 100);
        $pence = $minor % 100;
        $symbol = $currency === 'GBP' ? '£' : $currency.' ';

        return $symbol.number_format($major).'.'.str_pad((string) $pence, 2, '0', STR_PAD_LEFT);
    }
}
