<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShipperRequest extends FormRequest
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
        $recid = (int) $this->route('shipper');

        return [
            'cuscde' => [
                'required',
                'string',
                'max:25',
                Rule::unique('customerfile', 'cuscde')->ignore($recid, 'recid'),
            ],
            'cusdsc' => ['required', 'string', 'max:100'],
            'telno' => ['required', 'string', 'max:30'],
            'cusadd1' => ['required', 'string', 'max:100'],
            'tinnum' => ['required', 'string', 'max:30'],
            'cuspwd' => ['nullable', 'string', 'max:30'],
            'inactive' => ['sometimes', 'boolean'],
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
            'cusdsc.required' => 'This field cannot be blank',
            'telno.required' => 'This field cannot be blank',
            'cusadd1.required' => 'This field cannot be blank',
            'tinnum.required' => 'This field cannot be blank',
        ];
    }
}
