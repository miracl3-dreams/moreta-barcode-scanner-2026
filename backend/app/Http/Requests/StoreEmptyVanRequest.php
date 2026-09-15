<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmptyVanRequest extends FormRequest
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
            'catcde' => ['required', 'string', 'max:100'],
            'soc' => ['nullable', 'boolean'],
            'vannum' => ['nullable', 'string', 'max:30'],
            'socvannum' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'catcde.required' => 'Please fill out required fields',
        ];
    }
}
