<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBolRateRequest extends FormRequest
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
            'cuscde' => ['required', 'string', 'max:30'],
            'catcde' => ['required', 'string', 'max:30'],
            'remarks' => ['nullable', 'string', 'max:50'],
            'rate' => ['required'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cuscde.required' => 'Invalid Shipper.',
            'catcde.required' => 'Invalid User Category.',
            'rate.required' => 'Invalid User rate.',
        ];
    }
}
