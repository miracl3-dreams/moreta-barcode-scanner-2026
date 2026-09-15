<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVanTransportationRequest extends FormRequest
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
            'payee' => ['required', 'string', 'in:shipper,consignee'],
            'cuscde' => ['nullable', 'string', 'max:100', 'required_if:payee,shipper'],
            'cusdsc' => ['nullable', 'string', 'max:100', 'required_if:payee,shipper'],
            'concde' => ['nullable', 'string', 'max:50', 'required_if:payee,consignee'],
            'condsc' => ['nullable', 'string', 'max:100', 'required_if:payee,consignee'],
            'vannum' => ['required', 'string', 'max:30'],
            'eir' => ['nullable', 'string', 'max:20'],
            'origin' => ['required', 'string', 'max:30'],
            'dteout' => ['required', 'date'],
            'dstcde' => ['nullable', 'string', 'max:50'],
            'dteret' => ['nullable', 'date', 'after_or_equal:dteout'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dteout.required' => 'Date out required.',
            'origin.required' => 'Location of the van Required.',
            'vannum.required' => 'Van # Required.',
            'cuscde.required_if' => 'Shipper Required.',
            'cusdsc.required_if' => 'Shipper Required.',
            'concde.required_if' => 'Consignee Required.',
            'condsc.required_if' => 'Consignee Required.',
            'dteret.after_or_equal' => 'Date Return must be greater than date out.',
        ];
    }
}
