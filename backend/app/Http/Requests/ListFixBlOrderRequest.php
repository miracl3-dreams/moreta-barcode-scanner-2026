<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListFixBlOrderRequest extends FormRequest
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
            'voynum' => ['nullable', 'string', 'max:25'],
        ];
    }
}
