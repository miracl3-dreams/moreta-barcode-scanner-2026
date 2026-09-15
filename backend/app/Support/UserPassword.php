<?php

namespace App\Support;

use Illuminate\Support\Facades\Hash;

class UserPassword
{
    public static function matches(string $plain, ?string $usrpwd, ?string $pwdHash): bool
    {
        $plain = trim($plain);
        $hash = trim((string) $pwdHash);
        if ($hash !== '' && Hash::check($plain, $hash)) {
            return true;
        }

        // Legacy barcode_scanner/index.php: usrname + usrpwd exact match (trimmed).
        return $plain === trim((string) $usrpwd);
    }

    public static function hash(string $plain): string
    {
        return Hash::make($plain);
    }

    public static function usesHash(?string $pwdHash): bool
    {
        return trim((string) $pwdHash) !== '';
    }
}
