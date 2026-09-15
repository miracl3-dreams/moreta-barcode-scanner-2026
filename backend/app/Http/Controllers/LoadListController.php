<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoadListPdfRequest;
use App\Services\LoadListService;
use App\Support\LoadListPdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LoadListController extends Controller
{
    public function __construct(private LoadListService $loadList) {}

    public function lookups(): JsonResponse
    {
        return response()->json($this->loadList->lookups());
    }

    public function officePdf(LoadListPdfRequest $request): Response
    {
        return $this->pdf(fn () => $this->loadList->officePdf(
            trim((string) $request->validated('voynum')),
            trim((string) $request->validated('catcde')),
        ));
    }

    public function checkerPdf(LoadListPdfRequest $request): Response
    {
        return $this->pdf(fn () => $this->loadList->checkerPdf(
            trim((string) $request->validated('voynum')),
            trim((string) $request->validated('catcde')),
        ));
    }

    public function officeExport(LoadListPdfRequest $request): StreamedResponse|Response
    {
        return $this->export(fn () => $this->loadList->officeExport(
            trim((string) $request->validated('voynum')),
            trim((string) $request->validated('catcde')),
        ));
    }

    public function checkerExport(LoadListPdfRequest $request): StreamedResponse|Response
    {
        return $this->export(fn () => $this->loadList->checkerExport(
            trim((string) $request->validated('voynum')),
            trim((string) $request->validated('catcde')),
        ));
    }

    /**
     * @param  callable(): Response  $callback
     */
    private function pdf(callable $callback): Response
    {
        try {
            return $callback();
        } catch (ValidationException $e) {
            return LoadListPdf::errorResponse(
                collect($e->errors())->flatten()->first() ?: 'Unable to print.',
            );
        }
    }

    /**
     * @param  callable(): StreamedResponse  $callback
     */
    private function export(callable $callback): StreamedResponse|Response
    {
        try {
            return $callback();
        } catch (ValidationException $e) {
            return response(
                collect($e->errors())->flatten()->first() ?: 'Unable to export.',
                422,
            );
        }
    }
}
