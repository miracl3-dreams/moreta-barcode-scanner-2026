<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StatementAccountOutstandingRequest extends FormRequest
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
            'dstcde' => ['nullable', 'string', 'max:50'],
            'paytyp' => ['nullable', 'string', 'max:30'],
            'dtefrom' => ['nullable', 'string', 'max:20'],
            'dteto' => ['nullable', 'string', 'max:20'],
            'inc_main' => ['nullable'],
            'inc_cf' => ['nullable'],
        ];
    }
}
