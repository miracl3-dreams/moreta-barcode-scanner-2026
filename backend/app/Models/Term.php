<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Term extends Model
{
    protected $table = 'termfile';

    protected $primaryKey = 'recid';

    public $timestamps = false;

    /** Active mf_terms.php formfields plus recid. Never select the rest of termfile. */
    public const API_COLUMNS = [
        'recid',
        'trmcde',
        'trmdsc',
        'trmday',
    ];

    protected $fillable = [
        'trmcde',
        'trmdsc',
        'trmday',
    ];

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Term>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Term>
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
            'trmcde' => trim((string) ($this->trmcde ?? '')),
            'trmdsc' => trim((string) ($this->trmdsc ?? '')),
            'trmday' => $this->numberString($this->trmday),
        ];
    }

    private function numberString(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $number = (float) $value;
        if (abs($number - round($number)) < 0.0000001) {
            return (string) (int) round($number);
        }

        return rtrim(rtrim(sprintf('%.10F', $number), '0'), '.');
    }
}
