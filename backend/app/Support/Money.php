<?php

namespace App\Support;

use NumberFormatter;

// Formats integer minor units (MD §4) for people, e.g. 354000 INR → ₹3,540.00.
class Money
{
    public static function format(int $minor, string $currency, string $locale = 'en'): string
    {
        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
        $digits = $formatter->getAttribute(NumberFormatter::FRACTION_DIGITS);

        return (string) $formatter->formatCurrency($minor / (10 ** $digits), $currency);
    }
}
