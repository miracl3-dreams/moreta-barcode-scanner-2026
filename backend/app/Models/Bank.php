<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    protected $table = 'bankfile';

    protected $primaryKey = 'recid';

    public $timestamps = false;

    /** Active mf_bank.php formfields plus recid. Never select the rest of bankfile. */
    public const API_COLUMNS = [
        'recid',
        'bnkcde',
        'bnkdsc',
        'actcde',
    ];

    protected $fillable = [
        'bnkcde',
        'bnkdsc',
        'actcde',
    ];

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Bank>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Bank>
     */
    public function scopeApi($query)
    {
        return $query->select(self::API_COLUMNS);
    }

    public function resolveRouteBinding($value, $field = null): ?static
    {
        return static::query()->api()->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function toListArray(): array
    {
        return [
            'recid' => (int) $this->recid,
            'bnkcde' => (string) ($this->bnkcde ?? ''),
            'bnkdsc' => (string) ($this->bnkdsc ?? ''),
            'actcde' => (string) ($this->actcde ?? ''),
        ];
    }
}
