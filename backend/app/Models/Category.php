<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $table = 'categoryfile';

    protected $primaryKey = 'recid';

    public $timestamps = false;

    /** Active mf_category.php formfields plus recid. Never select the rest of categoryfile. */
    public const API_COLUMNS = [
        'recid',
        'catcde',
        'catdsc',
        'hasmax',
        'decvalmax',
        'iscontainer',
        'untmea',
        'meaamt',
        'tag',
    ];

    protected $fillable = [
        'catcde',
        'catdsc',
        'hasmax',
        'decvalmax',
        'iscontainer',
        'untmea',
        'meaamt',
        'tag',
    ];

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Category>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Category>
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
            'catcde' => trim((string) ($this->catcde ?? '')),
            'catdsc' => trim((string) ($this->catdsc ?? '')),
            'hasmax' => trim((string) ($this->hasmax ?? '')),
            'decvalmax' => $this->numberString($this->decvalmax),
            'iscontainer' => trim((string) ($this->iscontainer ?? '')),
            'untmea' => trim((string) ($this->untmea ?? '')),
            'meaamt' => $this->numberString($this->meaamt),
            'tag' => trim((string) ($this->tag ?? '')),
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
