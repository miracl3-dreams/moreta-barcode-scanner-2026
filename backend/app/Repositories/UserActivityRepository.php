<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserActivityRepository
{
    /**
     * @param  array<string, mixed>  $row
     */
    public function record(array $row, string $module = 'POS'): void
    {
        if (! Schema::hasTable('user_activities')) {
            return;
        }

        DB::table('user_activities')->insert($row);

        $max = 0;
        if (Schema::hasTable('system_parameters')) {
            $max = (int) (DB::table('system_parameters')->value('userlogmaxrec') ?? 0);
        }
        if ($max < 1) {
            return;
        }

        $count = (int) DB::table('user_activities')->where('module', $module)->count();
        if ($count <= $max) {
            return;
        }

        DB::delete(
            'DELETE FROM user_activities WHERE module = ? ORDER BY recid LIMIT '.($count - $max),
            [$module]
        );
    }
}
