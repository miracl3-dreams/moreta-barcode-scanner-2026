<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReplaceBlNumberRequest extends FormRequest
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
            'old_docnum' => ['nullable', 'string', 'max:25'],
            'new_docnum' => ['nullable', 'string', 'max:25'],
        ];
    }
}
