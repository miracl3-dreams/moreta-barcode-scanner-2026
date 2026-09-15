<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConsigneeRequest extends FormRequest
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
        $recid = (int) $this->route('consignee');

        return [
            'concde' => [
                'required',
                'string',
                'max:25',
                Rule::unique('consigneefile', 'concde')->ignore($recid, 'recid'),
            ],
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
            'concde.unique' => 'Code already exists in database!',
            'concde.required' => 'This field cannot be blank',
            'condsc.required' => 'This field cannot be blank',
            'telnum.required' => 'This field cannot be blank',
            'conadd1.required' => 'This field cannot be blank',
            'tinnum.required' => 'This field cannot be blank',
        ];
    }
}
