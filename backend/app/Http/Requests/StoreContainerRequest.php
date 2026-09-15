<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreContainerRequest extends FormRequest
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
        return ContainerRules::rules(true);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ContainerRules::messages();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            ContainerRules::uniquePrefixVan($validator, $this);
        });
    }
}
