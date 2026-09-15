<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchFixShiftRequest extends FormRequest
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
            'docnum' => ['nullable', 'string', 'max:25'],
            'date_from' => ['nullable', 'string', 'max:20'],
            'date_to' => ['nullable', 'string', 'max:20'],
            'shiftcde' => ['nullable', 'string', Rule::in(['', '1s', '2s', '3s'])],
            'trantype' => ['required', 'string', Rule::in(['exp', 'or'])],
        ];
    }
}
