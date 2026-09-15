<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Voyage extends Model
{
    protected $table = 'voyagefile';

    protected $primaryKey = 'recid';

    public $timestamps = false;

    /** Prepare BOL / voyage list fields plus recid. Never select the rest of voyagefile. */
    public const API_COLUMNS = [
        'recid',
        'voynum',
        'vsslcde',
        'trndte',
        'etadte',
        'origin',
        'dstcde',
        'dstdsc',
        'voytype',
    ];

    protected $fillable = [
        'voynum',
        'vsslcde',
        'trndte',
        'etadte',
        'origin',
        'dstcde',
        'dstdsc',
        'voytype',
    ];

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Voyage>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Voyage>
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
            'voynum' => trim((string) ($this->voynum ?? '')),
            'vsslcde' => trim((string) ($this->vsslcde ?? '')),
            'trndte' => $this->dateString($this->trndte),
            'etadte' => $this->dateString($this->etadte),
            'origin' => trim((string) ($this->origin ?? '')),
            'dstcde' => trim((string) ($this->dstcde ?? '')),
            'dstdsc' => trim((string) ($this->dstdsc ?? '')),
            'voytype' => trim((string) ($this->voytype ?? '')),
        ];
    }

    private function dateString(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '' || str_starts_with($text, '0000-00-00')) {
            return '';
        }

        return substr($text, 0, 10);
    }
}
