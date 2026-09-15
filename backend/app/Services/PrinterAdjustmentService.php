<?php

namespace App\Services;

use App\Repositories\PrinterAdjustmentRepository;

class PrinterAdjustmentService
{
    /**
     * @var list<string>
     */
    private const FIELDS = ['bol_top', 'bol_left', 'or_top', 'or_left', 'rep_top', 'rep_left'];

    public function __construct(private PrinterAdjustmentRepository $settings) {}

    /**
     * @return array{bol_top: int, bol_left: int, or_top: int, or_left: int, rep_top: int, rep_left: int}
     */
    public function show(): array
    {
        return $this->toArray($this->settings->current());
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{bol_top: int, bol_left: int, or_top: int, or_left: int, rep_top: int, rep_left: int}
     */
    public function save(array $validated): array
    {
        $values = [];
        foreach (self::FIELDS as $field) {
            $values[$field] = (int) ($validated[$field] ?? 0);
        }

        $this->settings->save($values);

        return $this->show();
    }

    /**
     * @return array{bol_top: int, bol_left: int, or_top: int, or_left: int, rep_top: int, rep_left: int}
     */
    private function toArray(?object $row): array
    {
        $out = [];
        foreach (self::FIELDS as $field) {
            $out[$field] = (int) ($row->{$field} ?? 0);
        }

        return $out;
    }
}
