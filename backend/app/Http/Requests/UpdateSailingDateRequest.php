<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSailingDateRequest extends FormRequest
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
            'trndte' => ['nullable', 'date_format:Y-m-d'],
            'etadte' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
