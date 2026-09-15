<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerActivityLog extends Model
{
    protected $table = 'customeractivitylogs';

    protected $primaryKey = 'recid';

    public $timestamps = false;

    /** Active view_cus_actlog.php queryFields plus recid. Never select the rest of customeractivitylogs. */
    public const API_COLUMNS = [
        'recid',
        'usrdte',
        'usrtim',
        'cuscde',
        'activity',
        'remarks',
    ];

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<CustomerActivityLog>  $query
     * @return \Illuminate\Database\Eloquent\Builder<CustomerActivityLog>
     */
    public function scopeApi($query)
    {
        return $query->select(self::API_COLUMNS);
    }

    /**
     * @return array<string, mixed>
     */
    public function toListArray(): array
    {
        return [
            'recid' => (int) $this->recid,
            'usrdte' => $this->listDate($this->usrdte ?? ''),
            'usrtim' => $this->listTime($this->usrtim ?? ''),
            'cuscde' => trim((string) ($this->cuscde ?? '')),
            'activity' => trim((string) ($this->activity ?? '')),
            'remarks' => trim((string) ($this->remarks ?? '')),
        ];
    }

    private function listDate(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '' || str_starts_with($text, '0000-00-00')) {
            return '';
        }

        $stamp = strtotime($text);
        if ($stamp === false) {
            return '';
        }

        return date('m-d-Y', $stamp);
    }

    private function listTime(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return '';
        }

        if (is_numeric($text)) {
            return date('h:i A', (int) $text);
        }

        $stamp = strtotime($text);
        if ($stamp === false || $stamp === -1) {
            return '';
        }

        return date('h:i A', $stamp);
    }
}
