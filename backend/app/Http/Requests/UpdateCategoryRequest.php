<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
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
        $category = $this->route('category');
        $recid = is_object($category) ? (int) $category->recid : (int) $category;

        return [
            'catcde' => [
                'required',
                'string',
                'max:50',
                Rule::unique('categoryfile', 'catcde')->ignore($recid, 'recid'),
            ],
            'catdsc' => ['required', 'string', 'max:100'],
            'hasmax' => ['required', 'string', 'max:1', Rule::exists('selectionfile', 'typecode')],
            'decvalmax' => ['required', 'numeric'],
            'iscontainer' => ['required', 'string', 'max:1', Rule::exists('selectionfile', 'typecode')],
            'untmea' => ['required', 'string', 'max:10'],
            'meaamt' => ['required', 'numeric'],
            'tag' => ['required', 'string', 'max:25'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'catcde.unique' => 'Code already exists in database!',
            'catcde.required' => 'This field cannot be blank',
            'catdsc.required' => 'This field cannot be blank',
            'hasmax.required' => 'This field cannot be blank',
            'decvalmax.required' => 'This field cannot be blank',
            'iscontainer.required' => 'This field cannot be blank',
            'untmea.required' => 'This field cannot be blank',
            'meaamt.required' => 'This field cannot be blank',
            'tag.required' => 'This field cannot be blank',
        ];
    }
}
