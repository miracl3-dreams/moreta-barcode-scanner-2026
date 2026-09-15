<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixBlNumberRepository
{
    public function existsDocnum(string $docnum): bool
    {
        return DB::table('billofladingfile1')->where('docnum', $docnum)->exists();
    }

    public function rename(string $oldDocnum, string $newDocnum, string $newDocapp): void
    {
        DB::table('billofladingfile1')->where('docnum', $oldDocnum)->update([
            'docnum' => $newDocnum,
            'docapp' => $newDocapp,
        ]);

        DB::table('billofladingfile2')->where('docnum', $oldDocnum)->update([
            'docnum' => $newDocnum,
        ]);

        if (Schema::hasTable('billofladingfile3')) {
            DB::table('billofladingfile3')->where('docnum', $oldDocnum)->update([
                'docnum' => $newDocnum,
            ]);
        }

        if (Schema::hasTable('vanlochistory') && Schema::hasColumn('vanlochistory', 'docnum')) {
            DB::table('vanlochistory')->where('docnum', $oldDocnum)->update([
                'docnum' => $newDocnum,
            ]);
        }

        $oldDocapp = 'SAL-'.$oldDocnum;
        if (Schema::hasTable('arpaymentapplication') && Schema::hasColumn('arpaymentapplication', 'docapp')) {
            DB::table('arpaymentapplication')->where('docapp', $oldDocapp)->update([
                'docapp' => $newDocapp,
            ]);
        }
    }
}
