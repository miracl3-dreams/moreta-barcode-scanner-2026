<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTermRequest extends FormRequest
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
            'trmcde' => ['required', 'string', 'max:20', Rule::unique('termfile', 'trmcde')],
            'trmdsc' => ['required', 'string', 'max:30'],
            'trmday' => ['required', 'numeric'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'trmcde.unique' => 'Code already exists in database!',
            'trmcde.required' => 'This field cannot be blank',
            'trmdsc.required' => 'This field cannot be blank',
            'trmday.required' => 'This field cannot be blank',
        ];
    }
}
