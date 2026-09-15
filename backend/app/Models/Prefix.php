<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prefix extends Model
{
    protected $table = 'prefixfile';

    protected $primaryKey = 'recid';

    public $timestamps = false;

    /** Active mf_prefix.php formfields plus recid. Never select the rest of prefixfile. */
    public const API_COLUMNS = [
        'recid',
        'prefix',
        'description',
    ];

    protected $fillable = [
        'prefix',
        'description',
    ];

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Prefix>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Prefix>
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
            'prefix' => trim((string) ($this->prefix ?? '')),
            'description' => trim((string) ($this->description ?? '')),
        ];
    }
}
