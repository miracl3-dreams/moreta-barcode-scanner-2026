<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBillingFormRequest;
use App\Models\BillingForm;
use App\Models\Customer;
use App\Services\BillingFormService;
use App\Support\BillingFormPdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BillingFormController extends Controller
{
    public function __construct(private BillingFormService $billings) {}

    public function lookups(Request $request): JsonResponse
    {
        return response()->json($this->billings->lookups($this->customer($request)));
    }

    public function consignees(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->billings->searchConsignees(trim((string) $request->query('q', ''))),
        ]);
    }

    public function eirs(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->billings->eirCandidates(
                $this->customer($request),
                trim((string) $request->query('q', '')),
            ),
        ]);
    }

    public function vanMeasurement(Request $request): JsonResponse
    {
        return response()->json($this->billings->vanMeasurement(trim((string) $request->query('vannum', ''))));
    }

    public function declaredValueLimit(Request $request): JsonResponse
    {
        return response()->json($this->billings->declaredValueLimit(
            trim((string) $request->query('vannum', '')),
            (string) $request->query('value', '0'),
        ));
    }

    public function weightLimit(Request $request): JsonResponse
    {
        return response()->json($this->billings->weightLimit(
            trim((string) $request->query('vannum', '')),
            (string) $request->query('weight', '0'),
            trim((string) $request->query('dstcde', '')),
        ));
    }

    public function pdf(Request $request): Response
    {
        $recid = (int) $request->query('recid', 0);
        $billing = $this->billings->owned($this->customer($request), $recid);
        if ($billing === null) {
            return BillingFormPdf::errorResponse('Billing form not present.');
        }

        return BillingFormPdf::render($this->billings->pdfPayload($billing));
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->billings->list(
            $this->customer($request),
            trim((string) $request->query('search', '')),
            $request->query('per_page', '5'),
            (string) $request->query('sort', ''),
            (string) $request->query('dir', 'desc'),
        ));
    }

    public function store(StoreBillingFormRequest $request): JsonResponse
    {
        return response()->json($this->billings->create($request->validated(), $this->customer($request)), 201);
    }

    public function show(Request $request, BillingForm $billing): JsonResponse
    {
        return response()->json($this->billings->show($this->ownedBilling($request, $billing)));
    }

    public function update(StoreBillingFormRequest $request, BillingForm $billing): JsonResponse
    {
        return response()->json(
            $this->billings->update($this->ownedBilling($request, $billing), $request->validated(), $this->customer($request)),
        );
    }

    public function destroy(Request $request, BillingForm $billing): JsonResponse
    {
        $this->billings->delete($this->ownedBilling($request, $billing));

        return response()->json(['ok' => true]);
    }

    private function customer(Request $request): Customer
    {
        /** @var Customer $customer */
        $customer = $request->user();

        return $customer;
    }

    private function ownedBilling(Request $request, BillingForm $billing): BillingForm
    {
        $owned = $this->billings->owned($this->customer($request), (int) $billing->recid);
        abort_if($owned === null, 404);

        return $owned;
    }
}
