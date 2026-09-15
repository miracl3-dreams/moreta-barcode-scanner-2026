<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EirFormSketch extends Model
{
    protected $table = 'eirtranfile2';

    protected $primaryKey = 'recid';

    public $timestamps = false;

    public const FLAGS = ['OK', 'LK', 'BE', 'BR', 'CR', 'DI', 'H', 'L', 'M', 'T', 'PI', 'C', 'PO', 'BO'];

    protected $fillable = [
        'docnum',
        'cont_sketch_desc',
        'OK',
        'LK',
        'BE',
        'BR',
        'CR',
        'DI',
        'H',
        'L',
        'M',
        'T',
        'PI',
        'C',
        'PO',
        'BO',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toSketchArray(): array
    {
        $flags = [];
        foreach (self::FLAGS as $flag) {
            $flags[strtolower($flag)] = (int) ($this->getAttribute($flag) ?? 0) === 1;
        }

        return [
            'desc' => trim((string) ($this->cont_sketch_desc ?? '')),
            'flags' => $flags,
        ];
    }
}
