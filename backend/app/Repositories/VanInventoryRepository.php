<?php

namespace App\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class VanInventoryRepository
{
    /**
     * @return list<string>
     */
    public function types(): array
    {
        return DB::table('vantypefile')
            ->orderBy('recid')
            ->pluck('vantype')
            ->filter()
            ->values()
            ->all();
    }

    public function companyName(): string
    {
        $row = DB::table('companyfile')->first();
        if (! $row) {
            return '';
        }

        if (isset($row->comdsc) && trim((string) $row->comdsc) !== '') {
            return (string) $row->comdsc;
        }

        return (string) ($row->companydescription ?? '');
    }

    /**
     * @param  list<string>  $columns
     * @return Collection<int, object>
     */
    public function reportRows(string $vantype, string $sortBy, string $sortDir, array $columns): Collection
    {
        $select = array_values(array_unique(array_merge(['recid'], $columns)));

        $query = DB::table('vanfile')->select($select)->where('remove', '<>', 'Y');
        if ($vantype !== '') {
            $query->where('vantype', $vantype);
        }

        return $query->orderBy($sortBy, $sortDir)->get();
    }

    /**
     * @param  list<string>  $columns
     * @return LazyCollection<int, object>
     */
    public function reportCursor(string $vantype, string $sortBy, string $sortDir, array $columns): LazyCollection
    {
        $select = array_values(array_unique(array_merge(['recid'], $columns)));

        $query = DB::table('vanfile')->select($select)->where('remove', '<>', 'Y');
        if ($vantype !== '') {
            $query->where('vantype', $vantype);
        }

        return $query->orderBy($sortBy, $sortDir)->cursor();
    }
}
