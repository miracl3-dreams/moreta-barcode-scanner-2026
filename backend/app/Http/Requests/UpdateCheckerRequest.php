<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCheckerRequest extends FormRequest
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
        $checker = $this->route('checker');
        $recid = is_object($checker) ? (int) $checker->recid : (int) $checker;

        return [
            'cuscde' => [
                'required',
                'string',
                'max:25',
                Rule::unique('checkerfile', 'cuscde')->ignore($recid, 'recid'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cuscde.unique' => 'Code already exists in database!',
            'cuscde.required' => 'This field cannot be blank',
        ];
    }
}
