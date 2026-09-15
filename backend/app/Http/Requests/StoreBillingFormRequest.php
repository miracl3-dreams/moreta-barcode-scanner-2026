<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBillingFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $user = $this->user();
        if ($user && isset($user->cuscde)) {
            $this->merge(['cuscde' => (string) $user->cuscde]);
        }

        $lines = $this->input('lines', []);
        if (! is_array($lines)) {
            return;
        }

        $filtered = [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $joined = trim(implode('', [
                (string) ($line['qty'] ?? ''),
                (string) ($line['unit'] ?? ''),
                (string) ($line['itmdesc'] ?? ''),
                (string) ($line['sealnum'] ?? ''),
                (string) ($line['weight'] ?? ''),
                (string) ($line['measurement'] ?? ''),
                (string) ($line['value'] ?? ''),
            ]));
            if ($joined !== '') {
                $filtered[] = $line;
            }
        }

        $this->merge(['lines' => $filtered]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'trndte' => ['required', 'date'],
            'del_dte' => ['required', 'date'],
            'dstcde' => ['required', 'string', 'max:50'],
            'cuscde' => ['required', 'string', 'max:100'],
            'cus_telno' => ['required', 'string', 'max:50'],
            'cus_email' => ['required', 'email', 'max:100'],
            'concde' => ['required', 'string', 'max:50'],
            'con_telno' => ['required', 'string', 'max:50'],
            'con_email' => ['required', 'email', 'max:100'],
            'eir_no' => ['required', 'string', 'max:20'],
            'original_eir' => ['nullable', 'string', 'max:20'],
            'decleared_by' => ['required', 'string', 'max:100'],
            'checked_by' => ['required', 'string', 'max:100'],
            'vannum' => ['required', 'string', 'max:20'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.qty' => ['nullable'],
            'lines.*.unit' => ['nullable', 'string', 'max:20'],
            'lines.*.itmdesc' => ['required', 'string', 'max:100'],
            'lines.*.sealnum' => ['required', 'string', 'max:50'],
            'lines.*.weight' => ['nullable', 'string', 'max:20'],
            'lines.*.measurement' => ['required', 'string', 'max:50'],
            'lines.*.value' => ['nullable'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cus_email.email' => 'Please check the Shipper Email',
            'con_email.email' => 'Please check the Consignee Email',
            'lines.required' => 'Please fill out all required fields',
            'lines.min' => 'Please fill out all required fields',
        ];
    }
}
