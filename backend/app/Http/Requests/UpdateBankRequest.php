<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBankRequest extends FormRequest
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
        $bank = $this->route('bank');
        $recid = is_object($bank) ? (int) $bank->recid : (int) $bank;

        return [
            'bnkcde' => [
                'required',
                'string',
                'max:25',
                Rule::unique('bankfile', 'bnkcde')->ignore($recid, 'recid'),
            ],
            'bnkdsc' => ['required', 'string', 'max:30'],
            'actcde' => ['required', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bnkcde.unique' => 'Code already exists in database!',
            'bnkcde.required' => 'This field cannot be blank',
            'bnkdsc.required' => 'This field cannot be blank',
            'actcde.required' => 'This field cannot be blank',
        ];
    }
}
