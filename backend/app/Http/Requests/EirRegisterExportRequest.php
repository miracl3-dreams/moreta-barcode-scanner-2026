<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EirRegisterExportRequest extends FormRequest
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
            'shipper' => ['nullable', 'string', 'max:150'],
            'consignee' => ['nullable', 'string', 'max:150'],
            'billing_no' => ['nullable', 'string', 'max:50'],
            'van_status' => ['nullable', 'string', 'max:50'],
        ];
    }
}
