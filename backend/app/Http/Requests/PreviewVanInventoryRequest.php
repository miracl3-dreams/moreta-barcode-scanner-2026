<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewVanInventoryRequest extends FormRequest
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
            'vantype' => ['nullable', 'string', 'max:50'],
            'sort_by' => ['nullable', 'string', 'max:30'],
            'sort_dir' => ['nullable', 'string', Rule::in(['ASC', 'DESC', 'asc', 'desc'])],
        ];
    }
}
