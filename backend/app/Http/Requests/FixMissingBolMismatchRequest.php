<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FixMissingBolMismatchRequest extends FormRequest
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
            'recids.required' => 'No missing CF rows available to fix for this voyage',
            'recids.min' => 'No missing CF rows available to fix for this voyage',
        ];
    }
}
