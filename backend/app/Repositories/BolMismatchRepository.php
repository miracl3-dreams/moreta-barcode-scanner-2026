<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BolMismatchRepository
{
    public function setGroupConcatMaxLen(): void
    {
        DB::statement('SET SESSION group_concat_max_len = 1000000');
    }

    /**
     * @return list<object>
     */
    public function cfHeaders(string $voynum): array
    {
        $query = DB::table('billofladingfile1')
            ->select(['recid', 'docnum', 'voynum', 'cuscde', 'concde', 'trndte'])
            ->where('voynum', 'like', '%CF%');

        if ($voynum !== '') {
            $like = '%'.$voynum.'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('voynum', 'like', $like)
                    ->orWhereRaw("REPLACE(voynum, 'CF', '') LIKE ?", [$like]);
            });
        }

        return $query->orderBy('voynum')->orderBy('docnum')->get()->all();
    }

    /**
     * @return list<object>
     */
    public function mainHeaders(string $voynum): array
    {
        $query = DB::table('billofladingfile1')
            ->select([
                'recid as main_recid',
                'voynum as main_voyage',
                'docnum as main_docnum',
                'cuscde as main_shipper',
                'concde as main_consignee',
                'trndte as main_trndte',
            ])
            ->where('voynum', 'not like', '%CF%');

        if ($voynum !== '') {
            $like = '%'.$voynum.'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('voynum', 'like', $like)
                    ->orWhereRaw("REPLACE(voynum, 'CF', '') LIKE ?", [$like]);
            });
        }

        return $query->orderBy('voynum')->orderBy('docnum')->get()->all();
    }

    /**
     * @return array{cnt: int, item_list: string|null}
     */
    public function itemProfile(string $docnum, string $voynum): array
    {
        $row = DB::selectOne(
            'SELECT COUNT(itmcde) AS cnt, GROUP_CONCAT(TRIM(itmcde) ORDER BY itmcde) AS item_list
             FROM billofladingfile2
             WHERE docnum = ? AND voynum = ?',
            [$docnum, $voynum]
        );

        return [
            'cnt' => (int) ($row->cnt ?? 0),
            'item_list' => $row->item_list ?? null,
        ];
    }

    /**
     * @return array{docnum: string, cuscde: string, concde: string}|null
     */
    public function findMainByItemList(string $mainVoyage, int $cnt, ?string $itemList): ?array
    {
        $row = DB::selectOne(
            'SELECT docnum, cuscde, concde
             FROM (
                 SELECT docnum, voynum, cuscde, concde,
                        COUNT(itmcde) AS cnt,
                        GROUP_CONCAT(TRIM(itmcde) ORDER BY itmcde) AS item_list
                 FROM billofladingfile2
                 WHERE voynum = ?
                 GROUP BY docnum, voynum, cuscde, concde
             ) m_agg
             WHERE m_agg.cnt = ? AND m_agg.item_list = ?
             LIMIT 1',
            [$mainVoyage, $cnt, $itemList]
        );

        if ($row === null) {
            return null;
        }

        return [
            'docnum' => (string) ($row->docnum ?? ''),
            'cuscde' => (string) ($row->cuscde ?? ''),
            'concde' => (string) ($row->concde ?? ''),
        ];
    }

    /**
     * @return array{docnum: string, cuscde: string, concde: string}|null
     */
    public function findMainHeader(string $mainVoyage, string $docnum): ?array
    {
        $row = DB::table('billofladingfile1')
            ->select(['docnum', 'cuscde', 'concde'])
            ->where('voynum', $mainVoyage)
            ->where('docnum', $docnum)
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'docnum' => (string) ($row->docnum ?? ''),
            'cuscde' => (string) ($row->cuscde ?? ''),
            'concde' => (string) ($row->concde ?? ''),
        ];
    }

    /**
     * @param  list<string>  $voynums
     * @return array<string, true>
     */
    public function existingVoyages(array $voynums): array
    {
        if ($voynums === []) {
            return [];
        }

        $found = [];
        foreach (DB::table('voyagefile')->whereIn('voynum', $voynums)->pluck('voynum') as $voynum) {
            $found[(string) $voynum] = true;
        }

        return $found;
    }

    /**
     * @param  list<string>  $voynums
     * @return array<string, true>
     */
    public function existingCfKeys(array $voynums): array
    {
        if ($voynums === []) {
            return [];
        }

        $found = [];
        foreach (DB::table('billofladingfile1')->whereIn('voynum', $voynums)->get(['docnum', 'voynum']) as $row) {
            $found[(string) $row->voynum.'|'.(string) $row->docnum] = true;
        }

        return $found;
    }

    public function headerDocByRecid(int $recid): ?object
    {
        return DB::table('billofladingfile1')->select(['docnum', 'voynum'])->where('recid', $recid)->first();
    }

    public function headerByRecid(int $recid): ?object
    {
        return DB::table('billofladingfile1')->where('recid', $recid)->first();
    }

    public function headerExists(string $docnum, string $voynum): bool
    {
        return DB::table('billofladingfile1')->where('docnum', $docnum)->where('voynum', $voynum)->exists();
    }

    public function renameHeaderDocnum(string $fromDocnum, string $toDocnum, string $toDocapp, string $voynum): void
    {
        DB::table('billofladingfile1')
            ->where('docnum', $fromDocnum)
            ->where('voynum', $voynum)
            ->update([
                'docnum' => $toDocnum,
                'docapp' => $toDocapp,
            ]);
    }

    public function renameLinesDocnum(string $fromDocnum, string $toDocnum, string $voynum): void
    {
        DB::table('billofladingfile2')
            ->where('docnum', $fromDocnum)
            ->where('voynum', $voynum)
            ->update(['docnum' => $toDocnum]);
    }

    public function updateHeaderDocnumByRecid(int $recid, string $docnum, string $docapp): void
    {
        DB::table('billofladingfile1')->where('recid', $recid)->update([
            'docnum' => $docnum,
            'docapp' => $docapp,
        ]);
    }

    /**
     * @param  list<string>  $voynums
     */
    public function deleteTmpForVoyages(array $voynums): void
    {
        if ($voynums === []) {
            return;
        }

        DB::table('billofladingfile2')->whereIn('voynum', $voynums)->where('docnum', 'like', 'TMP_%')->delete();
        DB::table('billofladingfile1')->whereIn('voynum', $voynums)->where('docnum', 'like', 'TMP_%')->delete();
    }

    /**
     * @return list<object>
     */
    public function linesByDocnumVoyage(string $docnum, string $voynum): array
    {
        return DB::table('billofladingfile2')->where('docnum', $docnum)->where('voynum', $voynum)->get()->all();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function insertHeader(array $row): void
    {
        $this->insertRecord('billofladingfile1', $row);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function insertLine(array $row): void
    {
        $this->insertRecord('billofladingfile2', $row);
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

    /**
     * @param  array<string, mixed>  $row
     */
    private function insertRecord(string $table, array $row): void
    {
        unset($row['recid']);
        $allowed = array_flip(Schema::getColumnListing($table));
        $payload = [];
        foreach ($row as $field => $value) {
            if (! isset($allowed[$field])) {
                continue;
            }
            if ($value === '' || $value === null) {
                $payload[$field] = null;

                continue;
            }
            $payload[$field] = is_string($value) ? stripslashes($value) : $value;
        }

        if ($payload === []) {
            return;
        }

        DB::table($table)->insert($payload);
    }
}
