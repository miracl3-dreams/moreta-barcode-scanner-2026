<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBillOfLadingRequest extends FormRequest
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
            'dsttelno' => ['required', 'string', 'min:7', 'max:50'],
            'payee' => ['required', 'string', 'in:shipper,consignee'],
            'paytrmcde' => ['nullable', 'string', 'max:30'],
            'shipmode' => ['nullable', 'string', 'max:25'],
            'remarks' => ['nullable', 'string', 'max:150'],
            'cuscde' => ['required', 'string', 'max:100'],
            'cusdsc' => ['required', 'string', 'max:100'],
            'concde' => ['required', 'string', 'max:50'],
            'condsc' => ['required', 'string', 'max:100'],
            'chkby' => ['required', 'string', 'max:30'],
            'cretrmcde' => ['nullable', 'string', 'max:10'],
            'cretrmdesc' => ['nullable', 'string', 'max:20'],
            'frghtamt' => ['nullable'],
            'vatamt' => ['nullable'],
            'arrorgamt' => ['nullable'],
            'arrdstamt' => ['nullable'],
            'ppaorgamt' => ['nullable'],
            'ppadstamt' => ['nullable'],
            'trckorgamt' => ['nullable'],
            'trckdstamt' => ['nullable'],
            'weiorgamt' => ['nullable'],
            'storamt' => ['nullable'],
            'dummamt' => ['nullable'],
            'othrchrgamt' => ['nullable'],
            'lines' => ['nullable', 'array', 'max:40'],
            'lines.*.linenum' => ['nullable', 'integer'],
            'lines.*.catcde' => ['nullable', 'string', 'max:100'],
            'lines.*.itmcde' => ['nullable', 'string', 'max:100'],
            'lines.*.qty' => ['nullable'],
            'lines.*.class' => ['nullable', 'string', 'max:30'],
            'lines.*.classdsc' => ['nullable', 'string', 'max:150'],
            'lines.*.profnum' => ['nullable', 'string', 'max:150'],
            'lines.*.soc' => ['nullable', 'boolean'],
            'lines.*.socvannum' => ['nullable', 'string', 'max:20'],
            'lines.*.vannum' => ['nullable', 'string', 'max:30'],
            'lines.*.sealnum' => ['nullable', 'string', 'max:30'],
            'lines.*.model' => ['nullable', 'string', 'max:20'],
            'lines.*.plate' => ['nullable', 'string', 'max:20'],
            'lines.*.constckr' => ['nullable', 'string', 'max:20'],
            'lines.*.value' => ['nullable'],
            'lines.*.weiamt' => ['nullable'],
            'lines.*.itmqty' => ['nullable'],
            'lines.*.untmea' => ['nullable', 'string', 'max:5'],
            'lines.*.untprc' => ['nullable'],
            'lines.*.extprc' => ['nullable'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dsttelno.required' => 'Please fill out required fields',
            'dsttelno.min' => 'Contact number should atleast 7 character!',
            'cuscde.required' => 'Please fill out required fields',
            'cusdsc.required' => 'Please fill out required fields',
            'concde.required' => 'Please fill out required fields',
            'condsc.required' => 'Please fill out required fields',
            'chkby.required' => 'Please fill out required fields',
            'payee.required' => 'Please fill out required fields',
        ];
    }
}
