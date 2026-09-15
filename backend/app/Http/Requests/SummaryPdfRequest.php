<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SummaryPdfRequest extends FormRequest
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
            'voynum' => ['nullable', 'string', 'max:50'],
            'paytrmcde' => ['nullable', 'string', 'max:50'],
            'cusdsc' => ['nullable', 'string', 'max:150'],
            'condsc' => ['nullable', 'string', 'max:150'],
            'catcde' => ['nullable', 'string', 'max:50'],
            'shipmode' => ['nullable', 'string', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable'],
        ];
    }
}
