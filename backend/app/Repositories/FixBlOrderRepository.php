<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class FixBlOrderRepository
{
    /**
     * @return list<string>
     */
    public function docnums(string $voynum): array
    {
        $out = [];
        foreach (DB::table('billofladingfile1')->where('voynum', $voynum)->orderBy('docnum')->get(['docnum']) as $row) {
            $out[] = (string) ($row->docnum ?? '');
        }

        return $out;
    }

    public function docnumExists(string $docnum): bool
    {
        return DB::table('billofladingfile1')->where('docnum', $docnum)->exists();
    }

    public function voyageBolnum(string $voynum): ?string
    {
        $row = DB::table('voyagefile')->where('voynum', $voynum)->first(['bolnum']);
        if ($row === null) {
            return null;
        }

        return (string) ($row->bolnum ?? '');
    }

    public function updateVoyageBolnum(string $voynum, string $bolnum): void
    {
        DB::table('voyagefile')->where('voynum', $voynum)->update([
            'bolnum' => $bolnum,
            'islock' => 'N',
        ]);
    }
}
