<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixShiftRepository
{
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

    /**
     * @param  array{shiftcde: string, docnum: string, date_from: string, date_to: string}  $filters
     * @return list<object>
     */
    public function search(string $table, array $filters): array
    {
        $query = DB::table($table)->select(['trndte', 'docnum', 'shiftcde', 'brnchcde']);

        if ($filters['shiftcde'] !== '') {
            $query->where('shiftcde', $filters['shiftcde']);
        }
        if ($filters['docnum'] !== '') {
            $query->where('docnum', $filters['docnum']);
        }
        if ($filters['date_from'] !== '') {
            $query->where('trndte', '>=', $filters['date_from']);
        }
        if ($filters['date_to'] !== '') {
            $query->where('trndte', '<=', $filters['date_to']);
        }

        return $query->get()->all();
    }

    public function currentCodes(string $table, string $docnum): ?object
    {
        return DB::table($table)->where('docnum', $docnum)->first(['shiftcde', 'brnchcde']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateByDocnum(string $table, string $docnum, array $attributes): void
    {
        DB::table($table)->where('docnum', $docnum)->update($attributes);
    }
}
