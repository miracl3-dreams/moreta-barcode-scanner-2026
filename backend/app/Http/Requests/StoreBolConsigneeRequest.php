<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBolConsigneeRequest extends FormRequest
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
            'condsc' => ['required', 'string', 'max:100'],
            'telnum' => ['required', 'string', 'max:100'],
            'conadd1' => ['required', 'string', 'max:100'],
            'tinnum' => ['required', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'condsc.required' => 'Some fields are required',
            'telnum.required' => 'Some fields are required',
            'conadd1.required' => 'Some fields are required',
            'tinnum.required' => 'Some fields are required',
        ];
    }
}
