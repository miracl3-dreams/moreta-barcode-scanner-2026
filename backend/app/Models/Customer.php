<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'customerfile';

    protected $primaryKey = 'recid';

    public $timestamps = false;

    protected $fillable = [
        'cuscde',
        'cusdsc',
        'cuspwd',
        'telno',
        'email',
        'inactive',
    ];

    protected $hidden = [
        'cuspwd',
        'pwd_hash',
    ];

    public function getAuthPassword(): string
    {
        return (string) $this->cuspwd;
    }

    public function passwordMatches(string $plain): bool
    {
        return \App\Support\UserPassword::matches(
            $plain,
            isset($this->cuspwd) ? (string) $this->cuspwd : null,
            isset($this->pwd_hash) ? (string) $this->pwd_hash : null,
        );
    }

    public function isInactive(): bool
    {
        return (int) ($this->inactive ?? 0) === 1;
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

    /**
     * @return array<string, mixed>
     */
    public function toAuthArray(): array
    {
        return [
            'cuscde' => trim((string) ($this->cuscde ?? '')),
            'cusdsc' => trim((string) ($this->cusdsc ?? '')),
            'telno' => trim((string) ($this->telno ?? '')),
            'email' => trim((string) ($this->email ?? '')),
        ];
    }
}
