<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReturnVanTransportationRequest;
use App\Http\Requests\StoreVanTransportationRequest;
use App\Models\VanTransportation;
use App\Services\VanTransportationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VanTransportationController extends Controller
{
    public function __construct(private VanTransportationService $transports) {}

    public function lookups(Request $request): JsonResponse
    {
        return response()->json($this->transports->lookups($request->user()));
    }

    public function payers(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->transports->searchPayers(
                trim((string) $request->query('type', 'shipper')),
                trim((string) $request->query('q', '')),
            ),
        ]);
    }

    public function vans(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->transports->searchVans(trim((string) $request->query('q', ''))),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->transports->list(
            trim((string) $request->query('search', '')),
            $request->query('per_page', '5'),
            (string) $request->query('sort', ''),
            (string) $request->query('dir', 'asc'),
        ));
    }

    public function store(StoreVanTransportationRequest $request): JsonResponse
    {
        $row = $this->transports->create($request->validated(), $request->user());

        return response()->json($row->toListArray(), 201);
    }

    public function show(VanTransportation $van_transport): JsonResponse
    {
        return response()->json($van_transport->toListArray());
    }

    public function update(StoreVanTransportationRequest $request, VanTransportation $van_transport): JsonResponse
    {
        $row = $this->transports->update($van_transport, $request->validated(), $request->user());

        return response()->json($row->toListArray());
    }

    public function returnVan(ReturnVanTransportationRequest $request, VanTransportation $van_transport): JsonResponse
    {
        $row = $this->transports->returnVan($van_transport, $request->validated(), $request->user());

        return response()->json($row->toListArray());
    }

    public function destroy(VanTransportation $van_transport, Request $request): JsonResponse
    {
        $this->transports->delete($van_transport, $request->user());

        return response()->json(['ok' => true]);
    }
}
