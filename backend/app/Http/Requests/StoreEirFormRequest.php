<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEirFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $sketch = $this->input('sketch');
        if (is_string($sketch)) {
            $decoded = json_decode($sketch, true);
            if (is_array($decoded)) {
                $this->merge(['sketch' => $decoded]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'origin' => ['required', 'string', 'max:30'],
            'booking_no' => ['nullable', 'string', 'max:20'],
            'vannum' => ['required', 'string', 'max:20'],
            'type' => ['nullable', 'string', 'max:50'],
            'size' => ['nullable', 'string', 'max:20'],
            'weight' => ['nullable', 'string', 'max:20'],
            'seal_no' => ['required', 'string', 'max:20'],
            'voynum' => ['nullable', 'string', 'max:30'],
            'cuscde' => ['required', 'string', 'max:100'],
            'concde' => ['required', 'string', 'max:50'],
            'issue_dte' => ['nullable', 'date'],
            'issue_time' => ['nullable', 'string', 'max:8'],
            'trucker' => ['required', 'string', 'max:100'],
            'driver_name' => ['required', 'string', 'max:100'],
            'plateno' => ['required', 'string', 'max:50'],
            'dstcde' => ['required', 'string', 'max:50'],
            'pickup' => ['nullable', 'string', 'max:20'],
            'return' => ['nullable', 'string', 'max:20'],
            'special_ins' => ['required', 'string', 'max:300'],
            'move_dte' => ['nullable', 'date'],
            'move_time' => ['required', 'string', 'max:8'],
            'van_received_by' => ['required', 'string', 'max:100'],
            'van_released_by' => ['required', 'string', 'max:100'],
            'sketch' => ['nullable', 'array'],
            'driver_sign' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:4096'],
            'checker_sign' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:4096'],
        ];
    }
}
