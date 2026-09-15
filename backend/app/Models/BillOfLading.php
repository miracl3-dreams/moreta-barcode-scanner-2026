<?php

namespace App\Models;

use App\Support\Amount;
use Illuminate\Database\Eloquent\Model;

class BillOfLading extends Model
{
    protected $table = 'billofladingfile1';

    protected $primaryKey = 'recid';

    public $timestamps = false;

    public const CHARGE_FIELDS = [
        'frghtamt',
        'vatamt',
        'arrorgamt',
        'arrdstamt',
        'ppaorgamt',
        'ppadstamt',
        'trckorgamt',
        'trckdstamt',
        'weiorgamt',
        'storamt',
        'dummamt',
        'othrchrgamt',
    ];

    /** Voyage shell BL list columns plus recid/voynum. Never select the rest of billofladingfile1. */
    public const API_COLUMNS = [
        'recid',
        'docnum',
        'voynum',
        'cusdsc',
        'condsc',
        'paytrmcde',
        'addby',
        'editby',
        'trntot',
    ];

    public const DETAIL_COLUMNS = [
        'recid',
        'docnum',
        'voynum',
        'voytyp',
        'trndte',
        'etadte',
        'origin',
        'dstcde',
        'vsslcde',
        'dsttelno',
        'payee',
        'paytrmcde',
        'shipmode',
        'remarks',
        'cuscde',
        'cusdsc',
        'concde',
        'condsc',
        'chkby',
        'typby',
        'addby',
        'editby',
        'cretrmcde',
        'cretrmdesc',
        'totpac',
        'subtot',
        'trntot',
        'docbal',
        'docbalfor',
        'origamt',
        'boldte',
        'billing_no',
        'frghtamt',
        'vatamt',
        'arrorgamt',
        'arrdstamt',
        'ppaorgamt',
        'ppadstamt',
        'trckorgamt',
        'trckdstamt',
        'weiorgamt',
        'storamt',
        'dummamt',
        'othrchrgamt',
    ];

    protected $fillable = [
        'voynum',
        'voytyp',
        'docnum',
        'trndte',
        'etadte',
        'usrnam',
        'addby',
        'editby',
        'docbal',
        'docbalfor',
        'trntotfor',
        'docapp',
        'currte',
        'origin',
        'dstcde',
        'vsslcde',
        'trncde',
        'paytyp',
        'origamt',
        'boldte',
        'totpac',
        'subtot',
        'trntot',
        'duedate',
        'dsttelno',
        'payee',
        'paytrmcde',
        'shipmode',
        'remarks',
        'cuscde',
        'cusdsc',
        'concde',
        'condsc',
        'billing_no',
        'chkby',
        'typby',
        'cretrmcde',
        'cretrmdesc',
        'frghtamt',
        'vatamt',
        'arrorgamt',
        'arrdstamt',
        'ppaorgamt',
        'ppadstamt',
        'trckorgamt',
        'trckdstamt',
        'weiorgamt',
        'storamt',
        'dummamt',
        'othrchrgamt',
    ];

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<BillOfLading>  $query
     * @return \Illuminate\Database\Eloquent\Builder<BillOfLading>
     */
    public function scopeApi($query)
    {
        return $query->select(self::API_COLUMNS);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<BillOfLading>  $query
     * @return \Illuminate\Database\Eloquent\Builder<BillOfLading>
     */
    public function scopeDetail($query)
    {
        return $query->select(self::DETAIL_COLUMNS);
    }

    /**
     * @return array<string, mixed>
     */
    public function toListArray(): array
    {
        return [
            'recid' => (int) $this->recid,
            'docnum' => trim((string) ($this->docnum ?? '')),
            'voynum' => trim((string) ($this->voynum ?? '')),
            'cusdsc' => trim((string) ($this->cusdsc ?? '')),
            'condsc' => trim((string) ($this->condsc ?? '')),
            'paytrmcde' => trim((string) ($this->paytrmcde ?? '')),
            'addby' => trim((string) ($this->addby ?? '')),
            'editby' => trim((string) ($this->editby ?? '')),
            'trntot' => Amount::format($this->trntot),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDetailArray(): array
    {
        $charges = [];
        foreach (self::CHARGE_FIELDS as $field) {
            $charges[$field] = Amount::display($this->{$field});
        }

        return [
            'recid' => (int) $this->recid,
            'docnum' => trim((string) ($this->docnum ?? '')),
            'voynum' => trim((string) ($this->voynum ?? '')),
            'dsttelno' => trim((string) ($this->dsttelno ?? '')),
            'payee' => trim((string) ($this->payee ?? '')),
            'paytrmcde' => trim((string) ($this->paytrmcde ?? '')),
            'shipmode' => trim((string) ($this->shipmode ?? '')),
            'remarks' => trim((string) ($this->remarks ?? '')),
            'cuscde' => trim((string) ($this->cuscde ?? '')),
            'cusdsc' => trim((string) ($this->cusdsc ?? '')),
            'concde' => trim((string) ($this->concde ?? '')),
            'condsc' => trim((string) ($this->condsc ?? '')),
            'chkby' => trim((string) ($this->chkby ?? '')),
            'typby' => trim((string) ($this->typby ?? '')),
            'addby' => trim((string) ($this->addby ?? '')),
            'editby' => trim((string) ($this->editby ?? '')),
            'cretrmcde' => trim((string) ($this->cretrmcde ?? '')),
            'cretrmdesc' => trim((string) ($this->cretrmdesc ?? '')),
            'totpac' => Amount::display($this->totpac),
            'subtot' => Amount::format($this->subtot),
            'trntot' => Amount::format($this->trntot),
            'charges' => $charges,
        ];
    }
}
