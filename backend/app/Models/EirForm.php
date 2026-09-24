<?php

namespace App\Models;

use App\Support\EirSignature;
use App\Support\LegacySchema;
use Illuminate\Database\Eloquent\Model;

class EirForm extends Model
{
    protected $table = 'eirtranfile1';

    protected $primaryKey = 'recid';

    public $timestamps = false;

    /** List and form fields used by view_equip_interchange_receipt.php. */
    public const API_COLUMNS = [
        'recid',
        'docnum',
        'booking_no',
        'origin',
        'vannum',
        'type',
        'size',
        'weight',
        'seal_no',
        'voynum',
        'cuscde',
        'concde',
        'issue_dte',
        'issue_time',
        'trucker',
        'driver_name',
        'plateno',
        'dstcde',
        'pickup',
        'return',
        'special_ins',
        'move_dte',
        'move_time',
        'van_received_by',
        'van_released_by',
        'accpt_dte',
        'billing_no',
        'rep_driver_sign',
        'checker_sign',
        'is_print',
    ];

    protected $fillable = [
        'docnum',
        'booking_no',
        'origin',
        'vannum',
        'type',
        'size',
        'weight',
        'seal_no',
        'voynum',
        'cuscde',
        'concde',
        'issue_dte',
        'issue_time',
        'trucker',
        'driver_name',
        'plateno',
        'dstcde',
        'pickup',
        'return',
        'special_ins',
        'move_dte',
        'move_time',
        'van_received_by',
        'van_released_by',
        'accpt_dte',
        'rep_driver_sign',
        'checker_sign',
        'is_print',
    ];

    /**
     * @return list<string>
     */
    public static function apiColumns(): array
    {
        $columns = [];
        foreach (self::API_COLUMNS as $column) {
            if ($column === 'recid' || LegacySchema::hasColumn('eirtranfile1', $column)) {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<EirForm>  $query
     * @return \Illuminate\Database\Eloquent\Builder<EirForm>
     */
    public function scopeApi($query)
    {
        return $query->select(static::apiColumns());
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
            'issue_dte' => $this->dateString($this->issue_dte),
            'origin' => trim((string) ($this->origin ?? '')),
            'vannum' => trim((string) ($this->vannum ?? '')),
            'billing_no' => trim((string) ($this->billing_no ?? '')),
            'accpt_dte' => $this->dateTimeString($this->accpt_dte),
            'is_print' => trim((string) ($this->is_print ?? '')),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $sketch
     * @return array<string, mixed>
     */
    public function toDetailArray(array $sketch): array
    {
        return [
            'recid' => (int) $this->recid,
            'docnum' => trim((string) ($this->docnum ?? '')),
            'booking_no' => trim((string) ($this->booking_no ?? '')),
            'origin' => trim((string) ($this->origin ?? '')),
            'vannum' => trim((string) ($this->vannum ?? '')),
            'type' => trim((string) ($this->type ?? '')),
            'size' => trim((string) ($this->size ?? '')),
            'weight' => trim((string) ($this->weight ?? '')),
            'seal_no' => trim((string) ($this->seal_no ?? '')),
            'voynum' => trim((string) ($this->voynum ?? '')),
            'cuscde' => trim((string) ($this->cuscde ?? '')),
            'concde' => trim((string) ($this->concde ?? '')),
            'issue_dte' => $this->dateString($this->issue_dte),
            'issue_time' => $this->timeString($this->issue_time),
            'trucker' => trim((string) ($this->trucker ?? '')),
            'driver_name' => trim((string) ($this->driver_name ?? '')),
            'plateno' => trim((string) ($this->plateno ?? '')),
            'dstcde' => trim((string) ($this->dstcde ?? '')),
            'pickup' => trim((string) ($this->getAttribute('pickup') ?? '')),
            'return' => trim((string) ($this->getAttribute('return') ?? '')),
            'special_ins' => trim((string) ($this->special_ins ?? '')),
            'move_dte' => $this->dateString($this->move_dte),
            'move_time' => $this->timeString($this->move_time),
            'van_received_by' => trim((string) ($this->van_received_by ?? '')),
            'van_released_by' => trim((string) ($this->van_released_by ?? '')),
            'accpt_dte' => $this->dateTimeString($this->accpt_dte),
            'billing_no' => trim((string) ($this->billing_no ?? '')),
            'rep_driver_sign' => EirSignature::normalize($this->rep_driver_sign ?? ''),
            'checker_sign' => EirSignature::normalize($this->checker_sign ?? ''),
            'is_print' => trim((string) ($this->is_print ?? '')),
            'sketch' => $sketch,
        ];
    }

    public function isTagged(): bool
    {
        return trim((string) ($this->billing_no ?? '')) !== '';
    }

    /**
     * Working lists (EIR browse + e-sign) only show documents that are not yet accepted.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<EirForm>|\Illuminate\Database\Query\Builder  $query
     */
    public static function applyUnaccepted($query): void
    {
        $query->where(function ($inner) {
            $inner->whereNull('accpt_dte')
                ->orWhere('accpt_dte', '')
                ->orWhere('accpt_dte', '0000-00-00')
                ->orWhere('accpt_dte', '0000-00-00 00:00:00')
                ->orWhere('accpt_dte', '<=', '1970-01-01 00:00:00');
        });
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<EirForm>  $query
     * @return \Illuminate\Database\Eloquent\Builder<EirForm>
     */
    public function scopeUnaccepted($query)
    {
        static::applyUnaccepted($query);

        return $query;
    }

    public static function hasAcceptanceDate(mixed $value): bool
    {
        if ($value instanceof \DateTimeInterface) {
            $text = $value->format('Y-m-d H:i:s');
        } else {
            $text = trim((string) ($value ?? ''));
        }
        if ($text === '' || str_starts_with($text, '0000-00-00')) {
            return false;
        }
        if (strcmp(substr($text, 0, 19), '1970-01-01 00:00:00') <= 0) {
            return false;
        }

        return true;
    }

    public function isAccepted(): bool
    {
        return static::hasAcceptanceDate($this->accpt_dte);
    }

    public function isPrinted(): bool
    {
        return trim((string) ($this->is_print ?? '')) === '1';
    }

    private function dateString(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '' || str_starts_with($text, '0000-00-00')) {
            return '';
        }

        return substr($text, 0, 10);
    }

    private function dateTimeString(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        $text = trim((string) ($value ?? ''));
        if ($text === '' || str_starts_with($text, '0000-00-00')) {
            return '';
        }

        return substr($text, 0, 19);
    }

    private function timeString(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return '';
        }

        return substr($text, 0, 8);
    }
}
