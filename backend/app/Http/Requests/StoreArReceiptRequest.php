<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreArReceiptRequest extends FormRequest
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
            'docnum' => ['required', 'string', 'max:15'],
            'payee' => ['required', 'string', 'in:shipper,consignee'],
            'payer_name' => ['required', 'string', 'max:100'],
            'payer_code' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'docnum.required' => 'O.R. No. is required.',
            'payer_name.required' => 'Payer name is required.',
            'payee.required' => 'Please select Shipper or Consignee.',
        ];
    }
}
