<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreArReceiptPaymentRequest extends FormRequest
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
            'paytyp' => ['required', 'string', 'max:30'],
            'bnkcde' => ['nullable', 'string', 'max:25'],
            'bnkdsc' => ['nullable', 'string', 'max:30'],
            'chknum' => ['nullable', 'string', 'max:25'],
            'refnum' => ['nullable', 'string', 'max:50'],
            'chkdte' => ['nullable', 'string', 'max:19'],
            'amount' => ['required', 'numeric'],
            'memtypcde' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'paytyp.required' => 'Pls. select Payment type.',
            'amount.required' => 'Must enter amount.',
        ];
    }
}
