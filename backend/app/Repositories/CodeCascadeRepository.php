<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Throwable;

class CodeCascadeRepository
{
    public function cascade(
        string $excludeTable,
        string $codeColumn,
        string $nameColumn,
        string $oldCode,
        string $newCode,
        string $newName,
    ): void {
        $schema = (string) DB::getDatabaseName();
        $tables = DB::select(
            'SELECT TABLE_NAME as table_name,
                    SUM(COLUMN_NAME = ?) as has_cde,
                    SUM(COLUMN_NAME = ?) as has_dsc
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ?
               AND COLUMN_NAME IN (?, ?)
               AND TABLE_NAME != ?
             GROUP BY TABLE_NAME',
            [$codeColumn, $nameColumn, $schema, $codeColumn, $nameColumn, $excludeTable]
        );

        foreach ($tables as $table) {
            $name = (string) $table->table_name;
            if (! preg_match('/^[A-Za-z0-9_]+$/', $name)) {
                continue;
            }

            try {
                if ((int) $table->has_cde === 1 && (int) $table->has_dsc === 1) {
                    DB::table($name)
                        ->where($codeColumn, $oldCode)
                        ->update([
                            $codeColumn => $newCode,
                            $nameColumn => $newName,
                        ]);
                } elseif ((int) $table->has_cde === 1 && $oldCode !== $newCode) {
                    DB::table($name)
                        ->where($codeColumn, $oldCode)
                        ->update([$codeColumn => $newCode]);
                }
            } catch (Throwable) {
                continue;
            }
        }
    }
}
