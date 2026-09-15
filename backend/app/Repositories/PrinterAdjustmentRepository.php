<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PrinterAdjustmentRepository
{
    /**
     * @return object{bol_top: mixed, bol_left: mixed, or_top: mixed, or_left: mixed, rep_top: mixed, rep_left: mixed}|null
     */
    public function current(): ?object
    {
        if (! Schema::hasTable('syspar4')) {
            return null;
        }

        return DB::table('syspar4')->first([
            'bol_top',
            'bol_left',
            'or_top',
            'or_left',
            'rep_top',
            'rep_left',
        ]);
    }

    /**
     * @param  array{bol_top: int, bol_left: int, or_top: int, or_left: int, rep_top: int, rep_left: int}  $values
     */
    public function save(array $values): void
    {
        if (! Schema::hasTable('syspar4')) {
            return;
        }

        DB::table('syspar4')->update($values);
    }
}
