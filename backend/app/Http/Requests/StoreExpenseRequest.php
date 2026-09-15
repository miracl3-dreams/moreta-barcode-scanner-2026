<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
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
            'itmdsc' => ['required', 'string', 'max:100'],
            'untprc' => ['required', 'numeric'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'itmdsc.required' => 'Description Required.',
            'untprc.required' => 'Amount Required.',
        ];
    }
}
