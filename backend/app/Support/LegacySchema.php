<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel Schema::hasTable() can return false on this MySQL server even when
 * the table is queryable. Probe with a real SELECT when information_schema lies.
 */
final class LegacySchema
{
    /** @var array<string, bool> */
    private static array $tables = [];

    /** @var array<string, bool> */
    private static array $columns = [];

    public static function hasTable(string $table): bool
    {
        if (array_key_exists($table, self::$tables)) {
            return self::$tables[$table];
        }

        try {
            if (Schema::hasTable($table)) {
                return self::$tables[$table] = true;
            }
        } catch (\Throwable) {
            // Fall through to a real SELECT.
        }

        try {
            DB::table($table)->limit(1)->get();

            return self::$tables[$table] = true;
        } catch (\Throwable) {
            return self::$tables[$table] = false;
        }
    }

    public static function hasColumn(string $table, string $column): bool
    {
        $key = $table.'.'.$column;
        if (array_key_exists($key, self::$columns)) {
            return self::$columns[$key];
        }

        if (! self::hasTable($table)) {
            return self::$columns[$key] = false;
        }

        try {
            if (Schema::hasColumn($table, $column)) {
                return self::$columns[$key] = true;
            }
        } catch (\Throwable) {
            // Fall through to a real SELECT of that column.
        }

        try {
            DB::table($table)->limit(1)->get([$column]);

            return self::$columns[$key] = true;
        } catch (\Throwable) {
            return self::$columns[$key] = false;
        }
    }

    public static function rememberColumn(string $table, string $column, bool $exists = true): void
    {
        self::$columns[$table.'.'.$column] = $exists;
    }

    public static function forgetColumn(string $table, string $column): void
    {
        unset(self::$columns[$table.'.'.$column]);
    }
}
