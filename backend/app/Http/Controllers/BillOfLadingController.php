<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListBolRatesRequest;
use App\Http\Requests\StoreBillOfLadingRequest;
use App\Http\Requests\StoreBolConsigneeRequest;
use App\Http\Requests\StoreBolRateRequest;
use App\Http\Requests\StoreBolSealRequest;
use App\Http\Requests\StoreBolShipperRequest;
use App\Http\Requests\StoreEmptyVanRequest;
use App\Models\Voyage;
use App\Services\BillOfLadingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillOfLadingController extends Controller
{
    public function __construct(private BillOfLadingService $bills) {}

    public function lookups(Request $request, Voyage $voyage): JsonResponse
    {
        return response()->json($this->bills->lookups($voyage, $request->user()));
    }

    public function freeVans(Request $request, Voyage $voyage): JsonResponse
    {
        return response()->json([
            'vans' => $this->bills->freeVans($voyage, trim((string) $request->query('catcde', ''))),
        ]);
    }

    public function searchSeals(Request $request): JsonResponse
    {
        return response()->json([
            'seals' => $this->bills->searchSeals(trim((string) $request->query('search', ''))),
        ]);
    }

    public function storeSeal(StoreBolSealRequest $request): JsonResponse
    {
        $this->bills->addSeal($request->validated());

        return response()->json(['ok' => true, 'message' => 'Success']);
    }

    public function rates(ListBolRatesRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->bills->rates($request->validated())]);
    }

    public function storeRate(StoreBolRateRequest $request): JsonResponse
    {
        return response()->json($this->bills->addRate($request->validated(), $request->user()));
    }

    public function storeShipper(StoreBolShipperRequest $request): JsonResponse
    {
        return response()->json($this->bills->addShipper($request->validated()), 201);
    }

    public function storeConsignee(StoreBolConsigneeRequest $request): JsonResponse
    {
        return response()->json($this->bills->addConsignee($request->validated()), 201);
    }

    public function printBill(Request $request, Voyage $voyage, int $bill): JsonResponse
    {
        return response()->json([
            'pages' => [$this->bills->printBill($voyage, $bill, $request->user())],
        ]);
    }

    public function printVoyage(Request $request, Voyage $voyage): JsonResponse
    {
        return response()->json([
            'pages' => $this->bills->printVoyage($voyage, $request->user()),
        ]);
    }

    public function store(StoreBillOfLadingRequest $request, Voyage $voyage): JsonResponse
    {
        return response()->json(
            $this->bills->create($voyage, $request->validated(), $request->user()),
            201,
        );
    }

    public function show(Request $request, Voyage $voyage, int $bill): JsonResponse
    {
        return response()->json($this->bills->show($voyage, $bill, $request->user()));
    }

    public function update(StoreBillOfLadingRequest $request, Voyage $voyage, int $bill): JsonResponse
    {
        return response()->json(
            $this->bills->update($voyage, $bill, $request->validated(), $request->user()),
        );
    }

    public function destroy(Request $request, Voyage $voyage, int $bill): JsonResponse
    {
        $this->bills->delete($voyage, $bill, $request->user());

        return response()->json(['ok' => true]);
    }

    public function emptyVans(Voyage $voyage): JsonResponse
    {
        return response()->json(['data' => $this->bills->emptyVans($voyage)]);
    }

    public function emptyVanContainers(Request $request, Voyage $voyage): JsonResponse
    {
        return response()->json([
            'containers' => $this->bills->emptyVanContainers(
                $voyage,
                trim((string) $request->query('catcde', '')),
            ),
        ]);
    }

    public function storeEmptyVan(StoreEmptyVanRequest $request, Voyage $voyage): JsonResponse
    {
        return response()->json($this->bills->createEmptyVan($voyage, $request->validated(), $request->user()), 201);
    }

    public function updateEmptyVan(StoreEmptyVanRequest $request, Voyage $voyage, int $emptyVan): JsonResponse
    {
        return response()->json($this->bills->updateEmptyVan($voyage, $emptyVan, $request->validated(), $request->user()));
    }

    public function destroyEmptyVan(Voyage $voyage, int $emptyVan, Request $request): JsonResponse
    {
        $this->bills->deleteEmptyVan($voyage, $emptyVan, $request->user());

        return response()->json(['ok' => true]);
    }
}
