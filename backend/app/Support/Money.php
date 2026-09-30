<?php

namespace App\Support;

use NumberFormatter;

// Formats integer minor units (MD §4) for people, e.g. 354000 INR → ₹3,540.00, and converts typed amounts back.
class Money
{
    public static function format(int $minor, string $currency, ?string $locale = null): string
    {
        $formatter = new NumberFormatter($locale ?? app()->getLocale(), NumberFormatter::CURRENCY);

        return (string) $formatter->formatCurrency($minor / (10 ** self::digits($currency)), $currency);
    }

    /** "1500.50" INR → 150050. Used for amounts customers type (wallet top-up). */
    public static function toMinor(string $amount, string $currency): int
    {
        return (int) round((float) $amount * (10 ** self::digits($currency)));
    }

    public static function digits(string $currency): int
    {
        $formatter = new NumberFormatter('en@currency='.$currency, NumberFormatter::CURRENCY);

        return (int) $formatter->getAttribute(NumberFormatter::FRACTION_DIGITS);
    }
}
