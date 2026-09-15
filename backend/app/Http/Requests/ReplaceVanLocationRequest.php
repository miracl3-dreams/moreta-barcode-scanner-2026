<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReplaceVanLocationRequest extends FormRequest
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
            'docnum' => ['nullable', 'string', 'max:100'],
            'vannum' => ['nullable', 'string', 'max:30'],
            'lastloc' => ['nullable', 'string', 'max:100'],
        ];
    }
}
