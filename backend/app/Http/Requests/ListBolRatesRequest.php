<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListBolRatesRequest extends FormRequest
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
            'payee' => ['required', 'string', 'in:shipper,consignee'],
            'cuscde' => ['nullable', 'string', 'max:100'],
            'cusdsc' => ['nullable', 'string', 'max:100'],
            'concde' => ['nullable', 'string', 'max:50'],
            'condsc' => ['nullable', 'string', 'max:100'],
        ];
    }
}
