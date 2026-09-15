<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FixBolMismatchRequest extends FormRequest
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
            'recids' => ['required', 'array', 'min:1'],
            'recids.*' => ['integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'recids.required' => 'No mismatch rows available to fix for this voyage',
            'recids.min' => 'No mismatch rows available to fix for this voyage',
        ];
    }
}
