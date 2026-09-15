<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StatementAccountByScRequest extends FormRequest
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
            'payee' => ['required', 'in:shipper,consignee'],
            'cuscde' => ['nullable', 'string', 'max:50'],
            'cusdsc' => ['nullable', 'string', 'max:150'],
            'concde' => ['nullable', 'string', 'max:50'],
            'condsc' => ['nullable', 'string', 'max:150'],
            'voynum' => ['nullable', 'string', 'max:50'],
            'dtefrom' => ['nullable', 'string', 'max:20'],
            'dteto' => ['nullable', 'string', 'max:20'],
            'withors' => ['nullable'],
            'withoutors' => ['nullable'],
            'inc_main' => ['nullable'],
            'inc_cf' => ['nullable'],
        ];
    }
}
