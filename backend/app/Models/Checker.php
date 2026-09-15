<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Checker extends Model
{
    protected $table = 'checkerfile';

    protected $primaryKey = 'recid';

    public $timestamps = false;

    /** Active mf_checker.php formfields plus recid. Never select the rest of checkerfile. */
    public const API_COLUMNS = [
        'recid',
        'cuscde',
    ];

    protected $fillable = [
        'cuscde',
    ];

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Checker>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Checker>
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
            'cuscde' => (string) ($this->cuscde ?? ''),
        ];
    }
}
