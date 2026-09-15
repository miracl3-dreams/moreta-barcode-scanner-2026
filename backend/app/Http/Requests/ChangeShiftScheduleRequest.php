<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeShiftScheduleRequest extends FormRequest
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
            'shiftcde' => ['required', 'string', Rule::in(['1s', '2s', '3s'])],
            'branchcde' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'shiftcde.required' => 'This field cannot be blank',
            'shiftcde.in' => 'This field cannot be blank',
        ];
    }
}
