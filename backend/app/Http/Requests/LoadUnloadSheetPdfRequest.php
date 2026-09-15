<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoadUnloadSheetPdfRequest extends FormRequest
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
            'txtvoynum' => ['nullable', 'string', 'max:50'],
            'voynum' => ['nullable', 'string', 'max:50'],
            'txtvoytype' => ['nullable', 'string', 'max:10'],
            'txtsort' => ['nullable', 'string', 'max:20'],
            'hid_filter' => ['nullable', 'string', 'max:20'],
        ];
    }
}
