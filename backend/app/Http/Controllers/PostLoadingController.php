<?php

namespace App\Http\Controllers;

use App\Http\Requests\PostLoadingPdfRequest;
use App\Http\Requests\PostLoadingUpdateRequest;
use App\Services\PostLoadingService;
use App\Support\PostLoadingPdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class PostLoadingController extends Controller
{
    public function __construct(private PostLoadingService $report) {}

    public function lookups(): JsonResponse
    {
        return response()->json($this->report->lookups());
    }

    public function lines(Request $request): JsonResponse
    {
        $voynum = trim((string) $request->query('voynum', ''));

        return response()->json(['lines' => $this->report->lines($voynum)]);
    }

    public function update(PostLoadingUpdateRequest $request): JsonResponse
    {
        $data = $request->validated();

        return $this->report->update((string) $data['voynum'], $data['lines']);
    }

    public function pdf(PostLoadingPdfRequest $request): Response
    {
        try {
            return $this->report->pdf($request->validated());
        } catch (ValidationException $e) {
            return PostLoadingPdf::errorResponse(
                collect($e->errors())->flatten()->first() ?: 'Unable to print.',
            );
        }
    }
}
