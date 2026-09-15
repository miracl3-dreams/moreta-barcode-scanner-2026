<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEirSigningSignatureRequest;
use App\Models\EirForm;
use App\Services\EirSigningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EirSigningController extends Controller
{
    public function __construct(private EirSigningService $signing) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->signing->list(
            trim((string) $request->query('search', '')),
            $request->query('per_page', 5),
            (string) $request->query('sort', ''),
            (string) $request->query('dir', 'desc'),
        ));
    }

    public function show(EirForm $eir_form): JsonResponse
    {
        return response()->json($this->signing->show($eir_form));
    }

    public function store(StoreEirSigningSignatureRequest $request, EirForm $eir_form): JsonResponse
    {
        $file = $request->file('signature');
        if ($file === null) {
            return response()->json([
                'message' => 'The signature file is required.',
            ], 422);
        }

        return response()->json($this->signing->store($eir_form, $file));
    }
}
