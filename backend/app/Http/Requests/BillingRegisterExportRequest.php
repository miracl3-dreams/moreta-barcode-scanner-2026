<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BillingRegisterExportRequest extends FormRequest
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
            'date_from' => ['nullable', 'string', 'max:20'],
            'date_to' => ['nullable', 'string', 'max:20'],
            'docnum_from' => ['nullable', 'string', 'max:50'],
            'docnum_to' => ['nullable', 'string', 'max:50'],
            'destination' => ['nullable', 'string', 'max:150'],
            'shipper' => ['nullable', 'string', 'max:150'],
            'consignee' => ['nullable', 'string', 'max:150'],
            'eir_docnum' => ['nullable', 'string', 'max:50'],
        ];
    }
}
