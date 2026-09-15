<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixVanLocationRepository
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public function destinations(): array
    {
        if (! Schema::hasTable('destinationfile')) {
            return [];
        }

        $out = [];
        foreach (DB::table('destinationfile')->orderBy('dstdsc')->get(['dstcde', 'dstdsc']) as $row) {
            $value = trim((string) ($row->dstcde ?? ''));
            if ($value !== '') {
                $out[] = [
                    'value' => $value,
                    'label' => trim((string) ($row->dstdsc ?? $value)),
                ];
            }
        }

        return $out;
    }

    public function updateVanLastloc(string $vannum, string $lastloc): void
    {
        DB::table('vanfile')->where('vannum', $vannum)->update([
            'lastloc' => $lastloc,
        ]);
    }

    public function findBolDocnum(string $docnum): ?string
    {
        $found = DB::table('billofladingfile1')->where('docnum', $docnum)->value('docnum');
        if ($found !== null && trim((string) $found) !== '') {
            return (string) $found;
        }

        $voyage = DB::table('billofladingfile1')->where('voynum', $docnum)->value('voynum');
        if ($voyage !== null && trim((string) $voyage) !== '') {
            return (string) $voyage;
        }

        return null;
    }

    public function updateHistory(string $destination, string $docnum, string $vannum): void
    {
        if (! Schema::hasTable('vanlochistory')) {
            return;
        }

        DB::table('vanlochistory')
            ->where('docnum', $docnum)
            ->where('vannum', $vannum)
            ->update(['destination' => $destination]);
    }
}
