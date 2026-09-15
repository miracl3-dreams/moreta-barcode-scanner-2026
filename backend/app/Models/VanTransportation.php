<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class VanTransportation extends Model
{
    protected $table = 'billofladingfile2';

    protected $primaryKey = 'recid';

    public $timestamps = false;

    /** List and form fields used by view_van_transportation.php. */
    public const API_COLUMNS = [
        'recid',
        'docnum',
        'usrnam',
        'trncde',
        'trndte',
        'dteout',
        'dteret',
        'vt_status',
        'payee',
        'cuscde',
        'cusdsc',
        'concde',
        'condsc',
        'vannum',
        'eir',
        'origin',
        'dstcde',
    ];

    protected $fillable = [
        'docnum',
        'usrnam',
        'trncde',
        'trndte',
        'dteout',
        'dteret',
        'vt_status',
        'payee',
        'cuscde',
        'cusdsc',
        'concde',
        'condsc',
        'vannum',
        'eir',
        'origin',
        'dstcde',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('van_transport', function (Builder $query) {
            $query->where('trncde', 'VT');
        });
    }

    /**
     * @param  Builder<VanTransportation>  $query
     * @return Builder<VanTransportation>
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
            'payee' => trim((string) ($this->payee ?? '')),
            'cuscde' => trim((string) ($this->cuscde ?? '')),
            'cusdsc' => trim((string) ($this->cusdsc ?? '')),
            'concde' => trim((string) ($this->concde ?? '')),
            'condsc' => trim((string) ($this->condsc ?? '')),
            'vannum' => trim((string) ($this->vannum ?? '')),
            'eir' => trim((string) ($this->eir ?? '')),
            'origin' => trim((string) ($this->origin ?? '')),
            'dteout' => $this->dateString($this->dteout),
            'dstcde' => trim((string) ($this->dstcde ?? '')),
            'dteret' => $this->dateString($this->dteret),
            'vt_status' => strtoupper(trim((string) ($this->vt_status ?? ''))),
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
