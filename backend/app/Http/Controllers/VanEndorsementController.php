<?php

namespace App\Http\Controllers;

use App\Http\Requests\VanEndorsementPdfRequest;
use App\Services\VanEndorsementService;
use Illuminate\Http\Response;

class VanEndorsementController extends Controller
{
    public function __construct(private VanEndorsementService $endorsement) {}

    public function pdf(VanEndorsementPdfRequest $request): Response
    {
        return $this->endorsement->pdf((int) $request->validated('recid'));
    }
}
