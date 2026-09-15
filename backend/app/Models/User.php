<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'userfile';

    protected $primaryKey = 'recid';

    public $timestamps = false;

    protected $fillable = [
        'usrcde',
        'usrname',
        'usrpwd',
        'pwd_hash',
        'usrlvl',
        'brnchcde',
        'status',
        'usrsessid',
        'usripaddress',
    ];

    protected $hidden = [
        'usrpwd',
        'pwd_hash',
    ];

    public function getAuthPassword(): string
    {
        return (string) $this->usrpwd;
    }

    public function passwordMatches(string $plain): bool
    {
        return \App\Support\UserPassword::matches(
            $plain,
            isset($this->usrpwd) ? (string) $this->usrpwd : null,
            isset($this->pwd_hash) ? (string) $this->pwd_hash : null,
        );
    }

    public function upgradePasswordHash(string $plain): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn($this->getTable(), 'pwd_hash')) {
            return;
        }

        $this->pwd_hash = \App\Support\UserPassword::hash($plain);
        $this->save();
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): ?string
    {
        return null;
    }

    public function isAdmin(): bool
    {
        return strtolower((string) $this->usrlvl) === 'supervisor';
    }

    /**
     * @return array<string, mixed>
     */
    public function toAuthArray(): array
    {
        return [
            'usrcde' => $this->usrcde,
            'usrname' => $this->usrname,
            'usrlvl' => $this->usrlvl,
            'brnchcde' => $this->brnchcde,
            'isadmin' => $this->isAdmin(),
        ];
    }
}
