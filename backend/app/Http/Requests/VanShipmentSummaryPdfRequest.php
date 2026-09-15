<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VanShipmentSummaryPdfRequest extends FormRequest
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
            'payee' => ['nullable', 'string', Rule::in(['shipper', 'consignee'])],
            'cuscde' => ['nullable', 'string', 'max:50'],
            'concde' => ['nullable', 'string', 'max:50'],
            'origin' => ['nullable', 'string', 'max:50'],
            'dstcde' => ['nullable', 'string', 'max:50'],
            'date_from' => ['nullable', 'string', 'max:20'],
            'date_to' => ['nullable', 'string', 'max:20'],
        ];
    }
}
