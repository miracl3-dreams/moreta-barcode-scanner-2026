<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Consignee extends Model
{
    protected $table = 'consigneefile';

    protected $primaryKey = 'recid';

    public $timestamps = false;

    /** Active mf_consignee.php formfields plus recid. Never select the rest of consigneefile. */
    public const API_COLUMNS = [
        'recid',
        'concde',
        'condsc',
        'telnum',
        'conadd1',
        'tinnum',
    ];

    protected $fillable = [
        'concde',
        'condsc',
        'telnum',
        'conadd1',
        'tinnum',
    ];

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Consignee>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Consignee>
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
            'concde' => (string) ($this->concde ?? ''),
            'condsc' => (string) ($this->condsc ?? ''),
            'telnum' => (string) ($this->telnum ?? ''),
            'conadd1' => (string) ($this->conadd1 ?? ''),
            'tinnum' => (string) ($this->tinnum ?? ''),
        ];
    }
}
