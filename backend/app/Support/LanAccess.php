<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class LanAccess
{
    public static function clientIp(Request $request): string
    {
        return (string) $request->ip();
    }

    public static function isPrivateIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return (bool) (
            preg_match('/^10\./', $ip)
            || preg_match('/^192\.168\./', $ip)
            || preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $ip)
            || preg_match('/^172\.88\./', $ip)
            || preg_match('/^172\.57\./', $ip)
        );
    }

    public static function isAllowed(Request $request): bool
    {
        $ip = self::clientIp($request);

        if (in_array($ip, ['127.0.0.1', '::1'], true)) {
            return true;
        }

        return self::isPrivateIp($ip);
    }

    public static function logDenied(Request $request): void
    {
        $ip = self::clientIp($request);
        $url = $request->fullUrl();

        try {
            if (! Schema::hasTable('access_denied_log')) {
                Schema::create('access_denied_log', function (Blueprint $table) {
                    $table->increments('id');
                    $table->string('ip_address', 45);
                    $table->dateTime('access_time');
                    $table->string('requested_url', 255);
                });
            }

            DB::table('access_denied_log')->insert([
                'ip_address' => $ip,
                'access_time' => now(),
                'requested_url' => $url,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Access denied log failed: '.$e->getMessage());
        }
    }
}
