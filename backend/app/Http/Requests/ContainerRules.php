<?php

namespace App\Http\Requests;

use App\Repositories\ContainerRepository;
use Illuminate\Validation\Validator;

class ContainerRules
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(bool $creating): array
    {
        $newVan = $creating
            ? ['required_if:vantype,Converted', 'nullable', 'string', 'max:30']
            : ['nullable', 'string', 'max:30'];

        return [
            'prefix' => ['nullable', 'string', 'max:20'],
            'vannum' => ['required_unless:vantype,Converted', 'nullable', 'string', 'max:30'],
            'vandsc' => ['nullable', 'string', 'max:50'],
            'vantype' => ['required', 'string', 'max:30'],
            'supplier' => ['required_if:vantype,Purchased,Rental', 'nullable', 'string', 'max:150'],
            'acqdte' => ['nullable', 'date'],
            'montopay' => ['nullable', 'numeric'],
            'start_lease' => ['nullable', 'date'],
            'end_lease' => ['nullable', 'date'],
            'contractno' => ['required_if:vantype,Lease to Own', 'nullable', 'string', 'max:50'],
            'contractdate' => ['required_if:vantype,Lease to Own', 'nullable', 'date'],
            'prevannum' => ['required_if:vantype,Converted,Fabricated', 'nullable', 'string', 'max:100'],
            'newvan0' => $newVan,
            'newvan1' => $newVan,
            'date_fabricated' => ['required_if:vantype,Fabricated', 'nullable', 'date'],
            'joborderno' => ['required_if:vantype,Scrapped', 'nullable', 'string', 'max:50'],
            'date_scrapped' => ['required_if:vantype,Scrapped', 'nullable', 'date'],
            'buyer' => ['required_if:vantype,Sold', 'nullable', 'string', 'max:50'],
            'date_sold' => ['required_if:vantype,Sold', 'nullable', 'date'],
            'start_rentaldate' => ['required_if:vantype,Rental', 'nullable', 'date'],
            'end_rentaldate' => ['required_if:vantype,Rental', 'nullable', 'date'],
            'remove' => ['sometimes'],
            'remarks' => ['nullable', 'string', 'max:200'],
            'lastloc' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        $previousVan = request('vantype') === 'Fabricated'
            ? 'Previous Van No. Required.'
            : 'Previous Van Number Required.';

        return [
            'vantype.required' => 'This field cannot be blank',
            'vannum.required' => 'Van number Required.',
            'vannum.required_unless' => 'Van number Required.',
            'supplier.required' => 'Supplier Required.',
            'supplier.required_if' => 'Supplier Required.',
            'prevannum.required' => $previousVan,
            'prevannum.required_if' => $previousVan,
            'contractno.required' => 'Contract No. Required.',
            'contractno.required_if' => 'Contract No. Required.',
            'contractdate.required' => 'Contract Date Required.',
            'contractdate.required_if' => 'Contract Date Required.',
            'date_fabricated.required' => 'Date Fabricated Required.',
            'date_fabricated.required_if' => 'Date Fabricated Required.',
            'joborderno.required' => 'Job Order No. Required.',
            'joborderno.required_if' => 'Job Order No. Required.',
            'date_scrapped.required' => 'Date Scrapped Required.',
            'date_scrapped.required_if' => 'Date Scrapped Required.',
            'buyer.required' => 'Buyer Required.',
            'buyer.required_if' => 'Buyer Required.',
            'date_sold.required' => 'Date Sold Required.',
            'date_sold.required_if' => 'Date Sold Required.',
            'start_rentaldate.required' => 'Start Rental Date Required.',
            'start_rentaldate.required_if' => 'Start Rental Date Required.',
            'end_rentaldate.required' => 'End Rental Date Required.',
            'end_rentaldate.required_if' => 'End Rental Date Required.',
            'newvan0.required' => 'New Van Nos. Required.',
            'newvan0.required_if' => 'New Van Nos. Required.',
            'newvan1.required' => 'New Van Nos. Required.',
            'newvan1.required_if' => 'New Van Nos. Required.',
        ];
    }

    public static function uniquePrefixVan(Validator $validator, mixed $request): void
    {
        $prefix = trim((string) $request->input('prefix', ''));
        $vannum = trim((string) $request->input('vannum', ''));
        $key = $prefix.$vannum;

        if (app(ContainerRepository::class)->existsByPrevannum($key)) {
            $validator->errors()->add('vannum', 'Van Number already exists with this prefix');
        }
    }
}
