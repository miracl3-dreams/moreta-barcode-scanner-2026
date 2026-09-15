<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\MenuPermission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceMenuPermission
{
    public function handle(Request $request, Closure $next, string $menprg, ?string $action = null): Response
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $resolved = $action ?? MenuPermission::actionFromRequest($request);
        MenuPermission::assert($user, $menprg, $resolved);

        return $next($request);
    }
}
