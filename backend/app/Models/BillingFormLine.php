<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingFormLine extends Model
{
    protected $table = 'billingtranfile2';

    protected $primaryKey = 'recid';

    public $timestamps = false;

    public const API_COLUMNS = [
        'recid',
        'docnum',
        'qty',
        'unit',
        'itmdesc',
        'sealnum',
        'weight',
        'measurement',
        'value',
    ];

    protected $fillable = [
        'docnum',
        'qty',
        'unit',
        'itmdesc',
        'sealnum',
        'weight',
        'measurement',
        'value',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toLineArray(): array
    {
        $qty = $this->qty;
        $value = $this->value;

        return [
            'recid' => (int) $this->recid,
            'qty' => $qty === null || $qty === '' ? '' : (string) $qty,
            'unit' => trim((string) ($this->unit ?? '')),
            'itmdesc' => trim((string) ($this->itmdesc ?? '')),
            'sealnum' => trim((string) ($this->sealnum ?? '')),
            'weight' => trim((string) ($this->weight ?? '')),
            'measurement' => trim((string) ($this->measurement ?? '')),
            'value' => $value === null || $value === '' ? '' : (string) $value,
        ];
    }
}
