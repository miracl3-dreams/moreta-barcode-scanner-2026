<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingForm extends Model
{
    protected $table = 'billingtranfile1';

    protected $primaryKey = 'recid';

    public $timestamps = false;

    /** List and form fields used by view_billing.php / view_add_billing.php. */
    public const API_COLUMNS = [
        'recid',
        'docnum',
        'trndte',
        'del_dte',
        'dstcde',
        'cuscde',
        'cus_telno',
        'cus_email',
        'concde',
        'con_telno',
        'con_email',
        'eir_no',
        'decleared_by',
        'checked_by',
        'vannum',
        'approvalstatus',
        'voynum_tag',
    ];

    protected $fillable = [
        'docnum',
        'trndte',
        'del_dte',
        'dstcde',
        'cuscde',
        'cus_telno',
        'cus_email',
        'concde',
        'con_telno',
        'con_email',
        'eir_no',
        'decleared_by',
        'checked_by',
        'vannum',
        'approvalstatus',
        'voynum_tag',
    ];

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<BillingForm>  $query
     * @return \Illuminate\Database\Eloquent\Builder<BillingForm>
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
            'trndte' => $this->dateString($this->trndte),
            'dstcde' => trim((string) ($this->dstcde ?? '')),
            'cuscde' => trim((string) ($this->cuscde ?? '')),
            'concde' => trim((string) ($this->concde ?? '')),
            'eir_no' => trim((string) ($this->eir_no ?? '')),
            'approvalstatus' => trim((string) ($this->approvalstatus ?? '')),
            'voynum_tag' => trim((string) ($this->voynum_tag ?? '')),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array<string, mixed>
     */
    public function toDetailArray(array $lines): array
    {
        return [
            'recid' => (int) $this->recid,
            'docnum' => trim((string) ($this->docnum ?? '')),
            'trndte' => $this->dateString($this->trndte),
            'del_dte' => $this->dateString($this->del_dte),
            'dstcde' => trim((string) ($this->dstcde ?? '')),
            'cuscde' => trim((string) ($this->cuscde ?? '')),
            'cus_telno' => trim((string) ($this->cus_telno ?? '')),
            'cus_email' => trim((string) ($this->cus_email ?? '')),
            'concde' => trim((string) ($this->concde ?? '')),
            'con_telno' => trim((string) ($this->con_telno ?? '')),
            'con_email' => trim((string) ($this->con_email ?? '')),
            'eir_no' => trim((string) ($this->eir_no ?? '')),
            'decleared_by' => trim((string) ($this->decleared_by ?? '')),
            'checked_by' => trim((string) ($this->checked_by ?? '')),
            'vannum' => trim((string) ($this->vannum ?? '')),
            'approvalstatus' => trim((string) ($this->approvalstatus ?? '')),
            'voynum_tag' => trim((string) ($this->voynum_tag ?? '')),
            'lines' => $lines,
        ];
    }

    public function isTagged(): bool
    {
        return trim((string) ($this->eir_no ?? '')) !== '';
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
