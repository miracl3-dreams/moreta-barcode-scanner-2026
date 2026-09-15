<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserFile extends Model
{
    protected $table = 'userfile';

    protected $primaryKey = 'recid';

    public $timestamps = false;

    /** Active view_userfile.php formfields plus recid. Never select the rest of userfile. */
    public const API_COLUMNS = [
        'recid',
        'usrcde',
        'usrname',
        'usrlvl',
        'brnchcde',
    ];

    public const FORM_COLUMNS = [
        'recid',
        'usrcde',
        'usrname',
        'usrpwd',
        'usrlvl',
        'brnchcde',
    ];

    protected $fillable = [
        'usrcde',
        'usrname',
        'usrpwd',
        'pwd_hash',
        'usrlvl',
        'brnchcde',
        'status',
    ];

    protected $hidden = [
        'usrpwd',
        'pwd_hash',
    ];

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<UserFile>  $query
     * @return \Illuminate\Database\Eloquent\Builder<UserFile>
     */
    public function scopeApi($query)
    {
        return $query->select(self::API_COLUMNS);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<UserFile>  $query
     * @return \Illuminate\Database\Eloquent\Builder<UserFile>
     */
    public function scopeForm($query)
    {
        return $query->select(self::FORM_COLUMNS);
    }

    public function resolveRouteBinding($value, $field = null): ?static
    {
        return static::query()->form()->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    public function isProtectedAdmin(): bool
    {
        return strtoupper(trim((string) $this->usrcde)) === 'ADMIN';
    }

    public function isSupervisorLevel(): bool
    {
        return strtoupper(trim((string) $this->usrlvl)) === 'SUPERVISOR';
    }

    /**
     * @return array<string, mixed>
     */
    public function toListArray(): array
    {
        $admin = $this->isProtectedAdmin();
        $supervisor = $this->isSupervisorLevel();

        return [
            'recid' => (int) $this->recid,
            'usrcde' => (string) ($this->usrcde ?? ''),
            'usrname' => (string) ($this->usrname ?? ''),
            'usrlvl' => (string) ($this->usrlvl ?? ''),
            'brnchcde' => (string) ($this->brnchcde ?? ''),
            'can_edit' => ! $admin,
            'can_access' => ! $admin && ! $supervisor,
            'can_reset' => ! $admin,
            'can_delete' => ! $admin && ! $supervisor,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toFormArray(): array
    {
        return [
            ...$this->toListArray(),
        ];
    }
}
