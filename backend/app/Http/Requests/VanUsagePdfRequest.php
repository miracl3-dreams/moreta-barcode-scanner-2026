<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VanUsagePdfRequest extends FormRequest
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
            'vannum' => ['nullable', 'string', 'max:100'],
            'origin' => ['nullable', 'string', 'max:50'],
            'date_from' => ['nullable', 'string', 'max:20'],
            'date_to' => ['nullable', 'string', 'max:20'],
            'include_soc' => ['nullable'],
        ];
    }
}
