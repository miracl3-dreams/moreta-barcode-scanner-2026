<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArReceipt extends Model
{
    protected $table = 'arpaymentsfile1';

    protected $primaryKey = 'recid';

    public $timestamps = false;

    /** List and header fields used by view_receipt_col.php / trn_receipt_col.php. */
    public const API_COLUMNS = [
        'recid',
        'docnum',
        'trndte',
        'payee',
        'payeedsc',
        'cuscde',
        'cusdsc',
        'concde',
        'condsc',
        'amount',
        'amountfor',
        'balance',
        'balancefor',
        'cancelled',
        'is_print',
        'bolnum',
        'trncde',
        'curcde',
        'currte',
        'shiftcde',
        'shiftdte',
        'brnchcde',
        'usrcde',
        'dirpay',
    ];

    protected $fillable = [
        'docnum',
        'trndte',
        'payee',
        'payeedsc',
        'cuscde',
        'cusdsc',
        'concde',
        'condsc',
        'trncde',
        'curcde',
        'currte',
        'shiftcde',
        'shiftdte',
        'brnchcde',
        'usrcde',
        'usrnam',
        'amount',
        'amountfor',
        'balance',
        'balancefor',
        'setbalance',
        'setbalancefor',
        'bolnum',
        'cancelled',
        'is_print',
        'dirpay',
    ];

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<ArReceipt>  $query
     * @return \Illuminate\Database\Eloquent\Builder<ArReceipt>
     */
    public function scopeApi($query)
    {
        return $query->select(self::API_COLUMNS)->where('trncde', 'ARP');
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
            'payee' => trim((string) ($this->payee ?? '')),
            'payeedsc' => trim((string) ($this->payeedsc ?? '')),
            'cuscde' => trim((string) ($this->cuscde ?? '')),
            'cusdsc' => trim((string) ($this->cusdsc ?? '')),
            'concde' => trim((string) ($this->concde ?? '')),
            'condsc' => trim((string) ($this->condsc ?? '')),
            'amount' => $this->money($this->amountfor ?? $this->amount),
            'balance' => $this->money($this->balancefor ?? $this->balance),
            'cancelled' => trim((string) ($this->cancelled ?? '')),
            'is_print' => (int) ($this->is_print ?? 0) === 1,
            'bolnum' => trim((string) ($this->bolnum ?? '')),
            'shiftcde' => trim((string) ($this->shiftcde ?? '')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDetailArray(): array
    {
        $list = $this->toListArray();
        $list['payer_name'] = strtolower($list['payee']) === 'consignee'
            ? ($list['condsc'] !== '' ? $list['condsc'] : $list['payeedsc'])
            : ($list['cusdsc'] !== '' ? $list['cusdsc'] : $list['payeedsc']);
        $list['payer_code'] = strtolower($list['payee']) === 'consignee' ? $list['concde'] : $list['cuscde'];

        return $list;
    }

    private function dateTimeString(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '' || str_starts_with($text, '0000-00-00')) {
            return '';
        }

        return substr($text, 0, 19);
    }

    private function money(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }
}
