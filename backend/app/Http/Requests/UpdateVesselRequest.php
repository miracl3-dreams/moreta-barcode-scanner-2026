<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVesselRequest extends FormRequest
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
        $recid = (int) $this->route('vessel');

        return [
            'vsslcde' => [
                'required',
                'string',
                'max:30',
                Rule::unique('vesselfile', 'vsslcde')->ignore($recid, 'recid'),
            ],
            'vssldsc' => ['required', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'vsslcde.unique' => 'Code already exists in database!',
            'vsslcde.required' => 'This field cannot be blank',
            'vssldsc.required' => 'This field cannot be blank',
        ];
    }
}
