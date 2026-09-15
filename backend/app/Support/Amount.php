<?php

namespace App\Support;

class Amount
{
    public static function parse(mixed $value): float
    {
        $text = str_replace(',', '', trim((string) ($value ?? '')));
        if ($text === '') {
            return 0.0;
        }

        return (float) $text;
    }

    public static function format(mixed $value): string
    {
        return number_format(self::parse($value), 2, '.', '');
    }

    public static function display(mixed $value): string
    {
        $number = self::parse($value);
        if (abs($number) < 0.0000001) {
            return '';
        }

        return rtrim(rtrim(sprintf('%.10F', $number), '0'), '.');
    }
}
