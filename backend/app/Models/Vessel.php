<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vessel extends Model
{
    protected $table = 'vesselfile';

    protected $primaryKey = 'recid';

    public $timestamps = false;

    /** Active mf_vessel.php formfields plus recid. Never select the rest of vesselfile. */
    public const API_COLUMNS = [
        'recid',
        'vsslcde',
        'vssldsc',
    ];

    protected $fillable = [
        'vsslcde',
        'vssldsc',
    ];

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Vessel>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Vessel>
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
            'vsslcde' => (string) ($this->vsslcde ?? ''),
            'vssldsc' => (string) ($this->vssldsc ?? ''),
        ];
    }
}
