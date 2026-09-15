<?php

namespace App\Support;

/**
 * Legacy eirtranfile1 stores the literal text "NULL" as the column default
 * for unsigned signature filenames (varchar NOT NULL DEFAULT 'NULL').
 */
final class EirSignature
{
    public static function isBlank(mixed $filename): bool
    {
        $value = strtoupper(trim((string) ($filename ?? '')));

        return $value === '' || $value === 'NULL';
    }

    public static function normalize(mixed $filename): string
    {
        return self::isBlank($filename) ? '' : trim((string) $filename);
    }
}
