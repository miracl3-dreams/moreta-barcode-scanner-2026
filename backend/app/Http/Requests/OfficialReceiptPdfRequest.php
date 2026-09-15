<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OfficialReceiptPdfRequest extends FormRequest
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
            'txt_ornumberfrom' => ['nullable', 'string', 'max:50'],
            'txt_ornumberto' => ['nullable', 'string', 'max:50'],
        ];
    }
}
