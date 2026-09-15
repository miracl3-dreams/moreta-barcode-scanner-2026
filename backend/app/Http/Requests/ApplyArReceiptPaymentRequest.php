<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplyArReceiptPaymentRequest extends FormRequest
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
            'lines' => ['required', 'array'],
            'lines.*.recid' => ['required', 'integer'],
            'lines.*.docapp' => ['nullable', 'string', 'max:50'],
            'lines.*.docnum' => ['nullable', 'string', 'max:50'],
            'lines.*.amtapp' => ['nullable', 'numeric'],
            'lines.*.trntot' => ['nullable', 'numeric'],
            'lines.*.ewt' => ['nullable', 'boolean'],
            'lines.*.evat' => ['nullable', 'boolean'],
            'lines.*.nonvat' => ['nullable', 'boolean'],
        ];
    }
}
