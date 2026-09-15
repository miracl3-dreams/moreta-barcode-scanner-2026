<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArReceiptPayment extends Model
{
    protected $table = 'arpaymentsfile2';

    protected $primaryKey = 'recid';

    public $timestamps = false;

    public const API_COLUMNS = [
        'recid',
        'docnum',
        'trncde',
        'paytyp',
        'bnkcde',
        'bnkdsc',
        'chknum',
        'refnum',
        'chkdte',
        'amount',
        'amountfor',
        'balance',
        'balancefor',
        'memtypcde',
        'cuscde',
        'cusdsc',
        'concde',
        'condsc',
        'payee',
        'curcde',
        'currte',
        'usrnam',
    ];

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<ArReceiptPayment>  $query
     * @return \Illuminate\Database\Eloquent\Builder<ArReceiptPayment>
     */
    public function scopeApi($query)
    {
        return $query->select(self::API_COLUMNS)->where('trncde', 'ARP');
    }

    /**
     * @return array<string, mixed>
     */
    public function toListArray(): array
    {
        return [
            'recid' => (int) $this->recid,
            'docnum' => trim((string) ($this->docnum ?? '')),
            'paytyp' => trim((string) ($this->paytyp ?? '')),
            'bnkcde' => trim((string) ($this->bnkcde ?? '')),
            'bnkdsc' => trim((string) ($this->bnkdsc ?? '')),
            'chknum' => trim((string) ($this->chknum ?? '')),
            'refnum' => trim((string) ($this->refnum ?? '')),
            'chkdte' => $this->dateString($this->chkdte),
            'amount' => number_format((float) ($this->amountfor ?? $this->amount ?? 0), 2, '.', ''),
            'balance' => number_format((float) ($this->balancefor ?? $this->balance ?? 0), 2, '.', ''),
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
