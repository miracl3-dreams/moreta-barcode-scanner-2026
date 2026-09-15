<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBolSealRequest extends FormRequest
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
            'usrcde' => ['required', 'string', 'max:20'],
            'usrpwd' => ['required', 'string', 'max:50'],
            'sealnum' => ['required', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sealnum.required' => 'Please fill out required fields',
        ];
    }
}
