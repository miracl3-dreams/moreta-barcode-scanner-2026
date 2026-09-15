<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePrefixRequest extends FormRequest
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
        $prefix = $this->route('prefix');
        $recid = is_object($prefix) ? (int) $prefix->recid : (int) $prefix;

        return [
            'prefix' => [
                'required',
                'string',
                'max:20',
                Rule::unique('prefixfile', 'prefix')->ignore($recid, 'recid'),
            ],
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
