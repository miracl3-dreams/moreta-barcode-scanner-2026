<?php

namespace App\Repositories;

use App\Models\User;
use App\Support\UserPassword;
use Illuminate\Support\Facades\Schema;

class SetPasswordRepository
{
    public function updatePassword(string $usrcde, string $plain): void
    {
        $attributes = [
            'usrpwd' => $plain,
        ];

        if (Schema::hasColumn('userfile', 'pwd_hash')) {
            $attributes['pwd_hash'] = UserPassword::hash($plain);
        }

        User::query()->where('usrcde', $usrcde)->update($attributes);
    }
}
