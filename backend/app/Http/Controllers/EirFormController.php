<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptEirFormRequest;
use App\Http\Requests\StoreEirFormRequest;
use App\Models\EirForm;
use App\Services\EirFormService;
use App\Support\EirFormPdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EirFormController extends Controller
{
    public function __construct(private EirFormService $eirs) {}

    public function lookups(Request $request): JsonResponse
    {
        return response()->json($this->eirs->lookups($request->user()));
    }

    public function containerSize(Request $request): JsonResponse
    {
        $size = $this->eirs->containerSize(trim((string) $request->query('vannum', '')));

        return response()->json(['size' => $size]);
    }

    public function pdf(Request $request): Response
    {
        $recid = (int) $request->query('recid', $request->query('txtprntid', 0));
        $eir = EirForm::query()->api()->where('recid', $recid)->first();
        if ($eir === null) {
            return EirFormPdf::errorResponse('EIR form not present.');
        }

        try {
            return EirFormPdf::render($this->eirs->pdfPayload($eir));
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?: 'Unable to print.';

            return EirFormPdf::errorResponse((string) $message);
        }
    }

    public function signature(EirForm $eir_form, string $kind): BinaryFileResponse|Response
    {
        $path = $this->eirs->signaturePath($eir_form, $kind);
        if ($path === null) {
            return response('Not found', 404);
        }

        return response()->file($path);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->eirs->list(
            trim((string) $request->query('search', '')),
            $request->query('per_page', '5'),
            (string) $request->query('sort', ''),
            (string) $request->query('dir', 'asc'),
        ));
    }

    public function store(StoreEirFormRequest $request): JsonResponse
    {
        return response()->json($this->eirs->create($request->validated(), $request->user()), 201);
    }

    public function show(EirForm $eir_form): JsonResponse
    {
        return response()->json($this->eirs->show($eir_form));
    }

    public function update(StoreEirFormRequest $request, EirForm $eir_form): JsonResponse
    {
        return response()->json($this->eirs->update($eir_form, $request->validated(), $request->user()));
    }

    public function destroy(EirForm $eir_form, Request $request): JsonResponse
    {
        $this->eirs->delete($eir_form, $request->user());

        return response()->json(['ok' => true]);
    }

    public function accept(AcceptEirFormRequest $request, EirForm $eir_form): JsonResponse
    {
        $data = $request->validated();

        return response()->json($this->eirs->accept(
            $eir_form,
            (string) $data['accpt_dte'],
            (string) $data['accpt_hour'],
            (string) $data['accpt_minute'],
            (string) $data['accpt_ampm'],
            $request->user(),
        ));
    }
}
