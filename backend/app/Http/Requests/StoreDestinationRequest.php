<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDestinationRequest extends FormRequest
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
            'dstcde' => ['required', 'string', 'max:25', Rule::unique('destinationfile', 'dstcde')],
            'dstdsc' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dstcde.unique' => 'Code already exists in database!',
            'dstcde.required' => 'This field cannot be blank',
            'dstdsc.required' => 'This field cannot be blank',
        ];
    }
}
