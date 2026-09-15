<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSetPasswordRequest extends FormRequest
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
            'oldpwd' => ['required', 'string', 'max:25'],
            'newpwd' => ['required', 'string', 'min:5', 'max:25'],
            'newpwd_confirmation' => ['required', 'string', 'max:25'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'oldpwd.required' => 'Please fill-up required item(s).',
            'newpwd.required' => 'Please fill-up required item(s).',
            'newpwd_confirmation.required' => 'Please fill-up required item(s).',
        ];
    }
}
