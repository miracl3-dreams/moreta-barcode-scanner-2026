<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveUserFileAccessRequest extends FormRequest
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
            'menus' => ['required', 'array'],
            'menus.*.checked' => ['required', 'boolean'],
            'menus.*.permissions' => ['sometimes', 'array'],
            'menus.*.permissions.allow_add' => ['sometimes', 'boolean'],
            'menus.*.permissions.allow_edit' => ['sometimes', 'boolean'],
            'menus.*.permissions.allow_delete' => ['sometimes', 'boolean'],
            'menus.*.permissions.allow_view' => ['sometimes', 'boolean'],
            'menus.*.permissions.allow_print' => ['sometimes', 'boolean'],
            'menus.*.permissions.allow_cancel' => ['sometimes', 'boolean'],
            'menus.*.permissions.allow_rates' => ['sometimes', 'boolean'],
            'menus.*.permissions.allow_approval' => ['sometimes', 'boolean'],
            'charges' => ['required', 'array', 'size:12'],
            'charges.*.editable' => ['required', 'boolean'],
        ];
    }
}
