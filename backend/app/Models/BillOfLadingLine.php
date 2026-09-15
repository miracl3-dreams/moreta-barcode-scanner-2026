<?php

namespace App\Models;

use App\Support\Amount;
use Illuminate\Database\Eloquent\Model;

class BillOfLadingLine extends Model
{
    protected $table = 'billofladingfile2';

    protected $primaryKey = 'recid';

    public $timestamps = false;

    public const CHARGE_LINE_COLUMNS = [
        'recid',
        'linenum',
        'catcde',
        'itmcde',
        'qty',
        'class',
        'classdsc',
        'profnum',
        'soc',
        'socvannum',
        'vannum',
        'sealnum',
        'model',
        'plate',
        'constckr',
        'value',
        'weiamt',
        'itmqty',
        'untmea',
        'untprc',
        'extprc',
        'voynum',
        'docnum',
        'trncde',
    ];

    public const EMPTY_VAN_COLUMNS = [
        'recid',
        'catcde',
        'soc',
        'socvannum',
        'vannum',
        'voynum',
        'trncde',
    ];

    protected $fillable = [
        'voynum',
        'voytyp',
        'docnum',
        'usrnam',
        'trndte',
        'vsslcde',
        'origin',
        'dstcde',
        'trncde',
        'payee',
        'concde',
        'cuscde',
        'chkr',
        'linenum',
        'catcde',
        'itmcde',
        'qty',
        'class',
        'classdsc',
        'profnum',
        'soc',
        'socvannum',
        'vannum',
        'sealnum',
        'model',
        'plate',
        'constckr',
        'value',
        'weiamt',
        'itmqty',
        'untmea',
        'untprc',
        'extprc',
        'dettyp',
        'adddte',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toCargoArray(): array
    {
        return [
            'recid' => (int) $this->recid,
            'linenum' => (int) ($this->linenum ?? 0),
            'catcde' => trim((string) ($this->catcde ?? '')),
            'itmcde' => trim((string) ($this->itmcde ?? '')),
            'qty' => Amount::display($this->qty),
            'class' => trim((string) ($this->class ?? '')),
            'classdsc' => trim((string) ($this->classdsc ?? '')),
            'profnum' => trim((string) ($this->profnum ?? '')),
            'soc' => strtoupper(trim((string) ($this->soc ?? ''))) === 'Y',
            'socvannum' => trim((string) ($this->socvannum ?? '')),
            'vannum' => trim((string) ($this->vannum ?? '')),
            'sealnum' => trim((string) ($this->sealnum ?? '')),
            'model' => trim((string) ($this->model ?? '')),
            'plate' => trim((string) ($this->plate ?? '')),
            'constckr' => trim((string) ($this->constckr ?? '')),
            'value' => Amount::display($this->value),
            'weiamt' => Amount::display($this->weiamt),
            'itmqty' => Amount::display($this->itmqty),
            'untmea' => trim((string) ($this->untmea ?? '')),
            'untprc' => Amount::display($this->untprc),
            'extprc' => Amount::display($this->extprc),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toEmptyVanArray(): array
    {
        return [
            'recid' => (int) $this->recid,
            'catcde' => trim((string) ($this->catcde ?? '')),
            'soc' => strtoupper(trim((string) ($this->soc ?? ''))) === 'Y',
            'socvannum' => trim((string) ($this->socvannum ?? '')),
            'vannum' => trim((string) ($this->vannum ?? '')),
        ];
    }
}
