<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PostLoadingUpdateRequest extends FormRequest
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
            'lines' => ['required', 'array'],
            'lines.*.docnum' => ['required', 'string', 'max:50'],
            'lines.*.vannum' => ['nullable', 'string', 'max:50'],
            'lines.*.reason' => ['nullable', 'string', 'max:255'],
            'lines.*.loadedon' => ['nullable', 'string', 'max:50'],
        ];
    }
}
