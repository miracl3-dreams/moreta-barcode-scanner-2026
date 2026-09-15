<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBolShipperRequest extends FormRequest
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
            'cusdsc' => ['required', 'string', 'max:100'],
            'telno' => ['required', 'string', 'max:30'],
            'cusadd1' => ['required', 'string', 'max:100'],
            'tinnum' => ['required', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cusdsc.required' => 'Some fields are required',
            'telno.required' => 'Some fields are required',
            'cusadd1.required' => 'Some fields are required',
            'tinnum.required' => 'Some fields are required',
        ];
    }
}
