<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApplyArReceiptPaymentRequest;
use App\Http\Requests\StoreArReceiptPaymentRequest;
use App\Http\Requests\StoreArReceiptRequest;
use App\Http\Requests\UpdateArReceiptBolnumRequest;
use App\Models\ArReceipt;
use App\Services\ArReceiptService;
use App\Support\ArReceiptPdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ArReceiptController extends Controller
{
    public function __construct(private ArReceiptService $receipts) {}

    public function lookups(Request $request): JsonResponse
    {
        return response()->json($this->receipts->lookups($request->user()));
    }

    public function payers(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->receipts->searchPayers(
                trim((string) $request->query('type', 'shipper')),
                trim((string) $request->query('q', '')),
            ),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->receipts->list(
            trim((string) $request->query('search', '')),
            $request->query('per_page', '5'),
            (string) $request->query('sort', ''),
            (string) $request->query('dir', 'asc'),
        ));
    }

    public function store(StoreArReceiptRequest $request): JsonResponse
    {
        return response()->json(
            $this->receipts->create($request->validated(), $request->user()),
            201,
        );
    }

    public function show(ArReceipt $receipt): JsonResponse
    {
        return response()->json($this->receipts->show($receipt));
    }

    public function destroy(ArReceipt $receipt, Request $request): JsonResponse
    {
        $this->receipts->delete($receipt, $request->user());

        return response()->json(['ok' => true]);
    }

    public function cancel(ArReceipt $receipt, Request $request): JsonResponse
    {
        return response()->json($this->receipts->cancel($receipt, $request->user()));
    }

    public function saveBolnum(UpdateArReceiptBolnumRequest $request, ArReceipt $receipt): JsonResponse
    {
        return response()->json($this->receipts->saveBolnum($receipt, $request->validated(), $request->user()));
    }

    public function storePayment(StoreArReceiptPaymentRequest $request, ArReceipt $receipt): JsonResponse
    {
        return response()->json(
            $this->receipts->addPayment($receipt, $request->validated(), $request->user()),
            201,
        );
    }

    public function destroyPayment(ArReceipt $receipt, int $payment, Request $request): JsonResponse
    {
        return response()->json($this->receipts->deletePayment($receipt, $payment, $request->user()));
    }

    public function applications(ArReceipt $receipt, int $payment): JsonResponse
    {
        return response()->json($this->receipts->applications($receipt, $payment));
    }

    public function apply(ApplyArReceiptPaymentRequest $request, ArReceipt $receipt, int $payment): JsonResponse
    {
        return response()->json($this->receipts->apply($receipt, $payment, $request->validated(), $request->user()));
    }

    public function pdf(Request $request): Response
    {
        return $this->streamPdf($request, false);
    }

    public function pdfNew(Request $request): Response
    {
        return $this->streamPdf($request, true);
    }

    private function streamPdf(Request $request, bool $newFormat): Response
    {
        $recid = (int) $request->query('txtprntid', $request->query('recid', 0));
        $receipt = ArReceipt::query()->api()->where('recid', $recid)->first();
        if ($receipt === null) {
            return ArReceiptPdf::errorResponse('O.R not present.');
        }

        return $this->receipts->pdf($receipt, $newFormat, [
            'htop' => (string) $request->query('htop', ''),
            'hleft' => (string) $request->query('hleft', ''),
            'dtop' => (string) $request->query('dtop', ''),
            'dleft' => (string) $request->query('dleft', ''),
        ]);
    }
}
