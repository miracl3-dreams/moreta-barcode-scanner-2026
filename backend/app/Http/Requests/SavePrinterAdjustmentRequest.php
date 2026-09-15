<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SavePrinterAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $merged = [];
        foreach (['bol_top', 'bol_left', 'or_top', 'or_left', 'rep_top', 'rep_left'] as $field) {
            $value = $this->input($field);
            if ($value === null || $value === '') {
                $merged[$field] = 0;
            }
        }
        if ($merged !== []) {
            $this->merge($merged);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bol_top' => ['required', 'integer'],
            'bol_left' => ['required', 'integer'],
            'or_top' => ['required', 'integer'],
            'or_left' => ['required', 'integer'],
            'rep_top' => ['required', 'integer'],
            'rep_left' => ['required', 'integer'],
        ];
    }
}
