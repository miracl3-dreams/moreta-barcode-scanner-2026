<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserFileRequest extends FormRequest
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
            'usrcde' => ['sometimes', 'string', 'max:15'],
            'usrname' => ['required', 'string', 'max:30'],
            'usrpwd' => ['nullable', 'string', 'max:30'],
            'usrlvl' => ['nullable', 'string', 'max:15'],
            'brnchcde' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'usrname.required' => 'This field cannot be blank',
            'usrpwd.required' => 'This field cannot be blank',
        ];
    }
}
