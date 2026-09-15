<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shipper extends Model
{
    protected $table = 'customerfile';

    protected $primaryKey = 'recid';

    public $timestamps = false;

    /** Active mf_shipper.php formfields plus recid. Never select the rest of customerfile. */
    public const API_COLUMNS = [
        'recid',
        'cuscde',
        'cusdsc',
        'telno',
        'cusadd1',
        'tinnum',
        'inactive',
    ];

    protected $fillable = [
        'cuscde',
        'cusdsc',
        'telno',
        'cusadd1',
        'tinnum',
        'cuspwd',
        'inactive',
    ];

    protected $hidden = [
        'cuspwd',
    ];

    protected $casts = [
        'inactive' => 'integer',
    ];

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Shipper>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Shipper>
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
            'cusdsc' => (string) ($this->cusdsc ?? ''),
            'telno' => (string) ($this->telno ?? ''),
            'cusadd1' => (string) ($this->cusadd1 ?? ''),
            'tinnum' => (string) ($this->tinnum ?? ''),
            'inactive' => (int) ($this->inactive ?? 0) === 1,
        ];
    }
}
