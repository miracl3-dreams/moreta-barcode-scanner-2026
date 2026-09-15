<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class Csv
{
    /**
     * @param  list<string|null>  $header
     * @param  array<string, string>  $aliases
     * @return array<string, int>
     */
    public static function headerMap(array $header, array $aliases): array
    {
        $map = [];
        foreach ($header as $index => $label) {
            $key = strtolower(trim((string) $label));
            if (isset($aliases[$key])) {
                $map[$aliases[$key]] = $index;
            }
        }

        return $map;
    }

    /**
     * @param  list<string|null>  $row
     * @param  array<string, int>  $map
     */
    public static function cell(array $row, array $map, string $field): string
    {
        if (! isset($map[$field])) {
            return '';
        }

        return trim((string) ($row[$map[$field]] ?? ''));
    }

    /**
     * @param  list<string|null>  $row
     */
    public static function rowEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{ok: true, header: list<string|null>, rows: list<list<string|null>>}|array{ok: false, message: string}
     */
    public static function read(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return ['ok' => false, 'message' => 'Unable to read uploaded file.'];
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);

            return ['ok' => false, 'message' => 'The file is empty.'];
        }

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return ['ok' => true, 'header' => $header, 'rows' => $rows];
    }

    /**
     * @param  list<list<string>>  $rows
     */
    public static function download(string $filename, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
