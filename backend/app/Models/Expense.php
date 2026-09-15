<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $table = 'receivingfile2';

    protected $primaryKey = 'recid';

    public $timestamps = false;

    /** List and form fields used by view_expenses.php. */
    public const API_COLUMNS = [
        'recid',
        'docnum',
        'trndte',
        'itmdsc',
        'untprc',
        'shiftcde',
        'brnchcde',
    ];

    protected $fillable = [
        'docnum',
        'trndte',
        'itmdsc',
        'untprc',
        'usrnam',
        'usrcde',
        'shiftcde',
        'shiftdte',
        'brnchcde',
    ];

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Expense>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Expense>
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
            'docnum' => trim((string) ($this->docnum ?? '')),
            'trndte' => $this->dateTimeString($this->trndte),
            'itmdsc' => trim((string) ($this->itmdsc ?? '')),
            'amount' => number_format((float) ($this->untprc ?? 0), 2, '.', ''),
            'shiftcde' => trim((string) ($this->shiftcde ?? '')),
        ];
    }

    private function dateTimeString(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '' || str_starts_with($text, '0000-00-00')) {
            return '';
        }

        return substr($text, 0, 19);
    }
}
