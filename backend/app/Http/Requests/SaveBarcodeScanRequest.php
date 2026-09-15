<?php

namespace App\Http\Requests;

use App\Models\EirFormSketch;
use App\Services\EirFormService;
use Illuminate\Foundation\Http\FormRequest;

class SaveBarcodeScanRequest extends FormRequest
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
        $flagRules = [];
        foreach (array_keys(EirFormService::SKETCH_PARTS) as $part) {
            foreach (array_map('strtolower', EirFormSketch::FLAGS) as $flag) {
                $flagRules["sketch.{$part}.{$flag}"] = ['nullable', 'boolean'];
            }
        }

        return array_merge([
            'code' => ['required', 'string', 'max:20'],
            'movetype' => ['required', 'string', 'in:pickup_mt,pickup_full,return_full,return_mt'],
            'dstcde' => ['required', 'string', 'max:50'],
            'voynum' => ['nullable', 'string', 'max:30'],
            'sketch' => ['required', 'array'],
        ], $flagRules);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'No scanned code detected.',
            'movetype.required' => 'Type of Move is required.',
            'dstcde.required' => 'Return Van To is required.',
        ];
    }
}
