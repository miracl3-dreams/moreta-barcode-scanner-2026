<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserActivityLog extends Model
{
    protected $table = 'user_activities';

    protected $primaryKey = 'recid';

    public $timestamps = false;

    /** Active view_actlog.php queryFields plus recid. Never select the rest of user_activities. */
    public const API_COLUMNS = [
        'recid',
        'usrdte',
        'usrtim',
        'usrcde',
        'activity',
        'remarks',
    ];

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<UserActivityLog>  $query
     * @return \Illuminate\Database\Eloquent\Builder<UserActivityLog>
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
            'usrcde' => trim((string) ($this->usrcde ?? '')),
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
