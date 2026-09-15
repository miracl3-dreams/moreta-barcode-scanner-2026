<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePrefixRequest extends FormRequest
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
            'prefix' => ['required', 'string', 'max:20', Rule::unique('prefixfile', 'prefix')],
            'description' => ['required', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prefix.unique' => 'Code already exists in database!',
            'prefix.required' => 'This field cannot be blank',
            'description.required' => 'This field cannot be blank',
        ];
    }
}
