<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AllowOrReprintRequest extends FormRequest
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
            'docnum' => ['required', 'string', 'max:25'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'docnum.required' => 'OR does not exist!',
        ];
    }
}
