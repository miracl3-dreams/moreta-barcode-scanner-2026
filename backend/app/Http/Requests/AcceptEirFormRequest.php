<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AcceptEirFormRequest extends FormRequest
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
            'accpt_dte' => ['required', 'date'],
            'accpt_hour' => ['required', 'string', 'max:2'],
            'accpt_minute' => ['required', 'string', 'max:2'],
            'accpt_ampm' => ['required', 'in:AM,PM'],
        ];
    }
}
