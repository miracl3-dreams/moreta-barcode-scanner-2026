<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveFixShiftRequest extends FormRequest
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
            'trantype' => ['required', 'string', Rule::in(['exp', 'or'])],
            'shiftcde_rep' => ['nullable', 'string', Rule::in(['', '1s', '2s', '3s'])],
            'branch' => ['nullable', 'string', 'max:100'],
            'docnums' => ['required', 'array'],
            'docnums.*' => ['string', 'max:25'],
        ];
    }
}
