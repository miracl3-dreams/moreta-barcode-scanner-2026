<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VanEndorsementPdfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $recid = $this->query('item_id', $this->query('recid', $this->input('item_id', $this->input('recid'))));
        $this->merge(['recid' => $recid]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recid' => ['required', 'integer'],
        ];
    }
}
