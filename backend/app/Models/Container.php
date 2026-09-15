<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Container extends Model
{
    protected $table = 'vanfile';

    protected $primaryKey = 'recid';

    public $timestamps = false;

    /** Active add_van.php fields plus list columns and recid. Never select the rest of vanfile. */
    public const API_COLUMNS = [
        'recid',
        'prefix',
        'vannum',
        'vandsc',
        'vantype',
        'supplier',
        'acqdte',
        'montopay',
        'start_lease',
        'end_lease',
        'contractno',
        'contractdate',
        'prevannum',
        'date_fabricated',
        'joborderno',
        'date_scrapped',
        'buyer',
        'date_sold',
        'start_rentaldate',
        'end_rentaldate',
        'remove',
        'remarks',
        'lastloc',
        'isConverted',
        'isFree',
    ];

    protected $fillable = [
        'prefix',
        'vannum',
        'vandsc',
        'vantype',
        'supplier',
        'acqdte',
        'montopay',
        'start_lease',
        'end_lease',
        'contractno',
        'contractdate',
        'prevannum',
        'date_fabricated',
        'joborderno',
        'date_scrapped',
        'buyer',
        'date_sold',
        'start_rentaldate',
        'end_rentaldate',
        'remove',
        'remarks',
        'lastloc',
        'isConverted',
        'isFree',
    ];

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Container>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Container>
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
            'prefix' => (string) ($this->prefix ?? ''),
            'vannum' => (string) ($this->vannum ?? ''),
            'vandsc' => (string) ($this->vandsc ?? ''),
            'vantype' => (string) ($this->vantype ?? ''),
            'supplier' => (string) ($this->supplier ?? ''),
            'acqdte' => $this->dateValue($this->acqdte),
            'montopay' => (float) ($this->montopay ?? 0),
            'start_lease' => $this->dateValue($this->start_lease),
            'end_lease' => $this->dateValue($this->end_lease),
            'contractno' => (string) ($this->contractno ?? ''),
            'contractdate' => $this->dateValue($this->contractdate),
            'prevannum' => (string) ($this->prevannum ?? ''),
            'date_fabricated' => $this->dateValue($this->date_fabricated),
            'joborderno' => (string) ($this->joborderno ?? ''),
            'date_scrapped' => $this->dateValue($this->date_scrapped),
            'buyer' => (string) ($this->buyer ?? ''),
            'date_sold' => $this->dateValue($this->date_sold),
            'start_rentaldate' => $this->dateValue($this->start_rentaldate),
            'end_rentaldate' => $this->dateValue($this->end_rentaldate),
            'remove' => strtoupper((string) ($this->remove ?? 'N')) === 'Y',
            'remarks' => (string) ($this->remarks ?? ''),
            'lastloc' => (string) ($this->lastloc ?? ''),
            'lastloc_locked' => (bool) $this->getAttribute('lastloc_locked'),
        ];
    }

    private function dateValue(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '' || str_starts_with($text, '0000-00-00')) {
            return '';
        }

        return substr($text, 0, 10);
    }
}
