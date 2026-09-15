<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContainerRequest extends FormRequest
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
        return ContainerRules::rules(false);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ContainerRules::messages();
    }
}
