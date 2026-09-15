<?php

namespace App\Http\Middleware;

use App\Support\LanAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceLanAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (LanAccess::isAllowed($request)) {
            return $next($request);
        }

        LanAccess::logDenied($request);

        return response()->json([
            'message' => 'Access denied. This system is restricted to users within the local network only.',
        ], 403);
    }
}
