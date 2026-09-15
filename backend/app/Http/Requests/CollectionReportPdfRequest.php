<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CollectionReportPdfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'par_search' => ['nullable', 'string', 'max:20'],
            'dtefrom' => ['nullable', 'string', 'max:20'],
            'dteto' => ['nullable', 'string', 'max:20'],
            'hrfrom' => ['nullable', 'string', 'max:2'],
            'minfrom' => ['nullable', 'string', 'max:2'],
            'hrto' => ['nullable', 'string', 'max:2'],
            'minto' => ['nullable', 'string', 'max:2'],
            'shiftcde' => ['nullable', 'string', 'max:10'],
            'canceldocs' => ['nullable'],
            'includezero' => ['nullable'],
            'cuscde' => ['nullable', 'string', 'max:50'],
            'concde' => ['nullable', 'string', 'max:50'],
        ];
    }
}
