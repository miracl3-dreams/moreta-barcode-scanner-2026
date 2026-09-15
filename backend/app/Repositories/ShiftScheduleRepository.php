<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ShiftScheduleRepository
{
    /**
     * @return object{shiftcde: mixed, shiftdte: mixed, usrcde: mixed}|null
     */
    public function syspar(): ?object
    {
        if (! Schema::hasTable('syspar7')) {
            return null;
        }

        return DB::table('syspar7')->first(['shiftcde', 'shiftdte', 'usrcde']);
    }

    /**
     * @return list<string>
     */
    public function usedShiftCodesToday(string $today): array
    {
        if (! Schema::hasTable('shiftchangefile1')) {
            return [];
        }

        $codes = [];
        foreach (DB::table('shiftchangefile1')->where('shiftdte', 'like', $today.'%')->get(['shiftcde']) as $row) {
            $code = trim((string) ($row->shiftcde ?? ''));
            if ($code !== '') {
                $codes[$code] = true;
            }
        }

        return array_keys($codes);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function branches(): array
    {
        if (! Schema::hasTable('destinationfile')) {
            return [];
        }

        $out = [];
        foreach (DB::table('destinationfile')->orderBy('dstcde')->get(['dstcde']) as $row) {
            $value = trim((string) ($row->dstcde ?? ''));
            if ($value !== '') {
                $out[] = ['value' => $value, 'label' => $value];
            }
        }

        return $out;
    }

    public function updateSyspar(string $shiftcde, string $shiftdte, string $usrcde): void
    {
        if (! Schema::hasTable('syspar7')) {
            return;
        }

        DB::table('syspar7')->update([
            'shiftcde' => $shiftcde,
            'shiftdte' => $shiftdte,
            'usrcde' => $usrcde,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function insertChange(array $attributes): void
    {
        if (! Schema::hasTable('shiftchangefile1')) {
            return;
        }

        DB::table('shiftchangefile1')->insert($attributes);
    }

    public function branchShift(string $branchcde): ?object
    {
        if (! Schema::hasTable('shift_branchfile') || $branchcde === '') {
            return null;
        }

        return DB::table('shift_branchfile')->where('branchcde', $branchcde)->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateBranchShift(string $branchcde, array $attributes): void
    {
        DB::table('shift_branchfile')->where('branchcde', $branchcde)->update($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function insertBranchShift(array $attributes): void
    {
        DB::table('shift_branchfile')->insert($attributes);
    }
}
