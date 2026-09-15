<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Destination extends Model
{
    protected $table = 'destinationfile';

    protected $primaryKey = 'recid';

    public $timestamps = false;

    /** Active mf_destination.php formfields plus recid. Never select the rest of destinationfile. */
    public const API_COLUMNS = [
        'recid',
        'dstcde',
        'dstdsc',
    ];

    protected $fillable = [
        'dstcde',
        'dstdsc',
    ];

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Destination>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Destination>
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
            'dstcde' => (string) ($this->dstcde ?? ''),
            'dstdsc' => (string) ($this->dstdsc ?? ''),
        ];
    }
}
