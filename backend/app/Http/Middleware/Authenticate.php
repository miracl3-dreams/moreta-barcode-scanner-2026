<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    protected function redirectTo(Request $request): ?string
    {
        return $request->expectsJson() ? null : rtrim(env('FRONTEND_URL', 'http://localhost:5175'), '/').'/barcode_scanner_2026/login';
    }
}
