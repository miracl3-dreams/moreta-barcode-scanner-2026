<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PostLoadingPdfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'voynum' => $this->query('voynum', $this->input('voynum', $this->input('txtfld.voynum'))),
            'catcde' => $this->query('catcde', $this->input('catcde', $this->input('txtfld.catcde'))),
            'copyfor' => $this->query('copyfor', $this->input('copyfor', $this->input('txtfld.copyfor'))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'voynum' => ['required', 'string', 'max:50'],
            'catcde' => ['required', 'string', 'max:50'],
            'copyfor' => ['required', 'string', 'max:20'],
        ];
    }
}
