<?php

namespace App\Support;

class Text
{
    public static function clip(mixed $value, int $max): string
    {
        return mb_substr(trim((string) ($value ?? '')), 0, $max);
    }
}
