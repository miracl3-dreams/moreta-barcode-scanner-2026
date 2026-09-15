<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;

class UserfileUserProvider extends EloquentUserProvider
{
    /**
     * Match webmoreta: htmlentities() on both the submitted password and usrpwd.
     */
    public function validateCredentials(UserContract $user, array $credentials): bool
    {
        $plain = $credentials['password'] ?? '';

        return htmlentities($plain) === htmlentities((string) $user->getAuthPassword());
    }
}
