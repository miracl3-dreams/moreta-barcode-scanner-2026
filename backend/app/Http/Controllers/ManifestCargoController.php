<?php

namespace App\Http\Controllers;

use App\Http\Requests\ManifestCargoPdfRequest;
use App\Services\ManifestCargoService;
use Illuminate\Http\Response;

class ManifestCargoController extends Controller
{
    public function __construct(private ManifestCargoService $manifest) {}

    public function pdf(ManifestCargoPdfRequest $request): Response
    {
        $voynum = trim((string) $request->validated('voynum'));
        $type = trim((string) ($request->validated('xtype') ?? 'office'));

        return $type === 'checker'
            ? $this->manifest->checkerPdf($voynum)
            : $this->manifest->officePdf($voynum);
    }
}
