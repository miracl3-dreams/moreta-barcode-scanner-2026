<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVoyageRequest extends FormRequest
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
            'voynum' => ['required', 'string', 'max:15', Rule::unique('voyagefile', 'voynum')],
            'trndte' => ['required', 'date_format:Y-m-d'],
            'etadte' => ['nullable', 'date_format:Y-m-d'],
            'origin' => ['required', 'string', 'max:30'],
            'dstcde' => ['required', 'string', 'max:50'],
            'vsslcde' => ['nullable', 'string', 'max:30'],
            'voytype' => ['nullable', 'string', 'max:50'],
            'duplicate' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'voynum.unique' => 'Duplicate Voyage Number.',
            'voynum.required' => 'Voyage Number Required',
            'trndte.required' => 'Sailing Date Required.',
            'origin.required' => 'Origin Required.',
            'dstcde.required' => 'Destination Required.',
        ];
    }
}
