<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StatementAccountByVoyageRequest extends FormRequest
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
            'voynum' => ['required', 'string', 'max:50'],
            'paytyp' => ['nullable', 'string', 'max:30'],
        ];
    }
}
