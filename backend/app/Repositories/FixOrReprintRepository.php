<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixOrReprintRepository
{
    public function findRecidByDocnum(string $docnum): ?int
    {
        $recid = DB::table('arpaymentsfile1')->where('docnum', $docnum)->value('recid');
        if ($recid === null) {
            return null;
        }

        return (int) $recid;
    }

    public function allowReprint(int $recid): void
    {
        DB::table('arpaymentsfile1')->where('recid', $recid)->update([
            'is_print' => 0,
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function insertActivity(array $row): void
    {
        if (! Schema::hasTable('user_activities')) {
            return;
        }

        DB::table('user_activities')->insert($row);
    }

    public function activityCountForModule(string $module): int
    {
        if (! Schema::hasTable('user_activities')) {
            return 0;
        }

        return (int) DB::table('user_activities')->where('module', $module)->count();
    }

    public function pruneActivities(string $module, int $excess): void
    {
        if ($excess < 1 || ! Schema::hasTable('user_activities')) {
            return;
        }

        DB::delete(
            'DELETE FROM user_activities WHERE module = ? ORDER BY recid LIMIT '.$excess,
            [$module]
        );
    }

    public function userLogMaxRec(): int
    {
        if (! Schema::hasTable('system_parameters')) {
            return 0;
        }

        $value = DB::table('system_parameters')->value('userlogmaxrec');

        return (int) ($value ?? 0);
    }
}
