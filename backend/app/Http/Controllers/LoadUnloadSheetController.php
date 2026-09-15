<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoadUnloadSheetPdfRequest;
use App\Services\LoadUnloadSheetService;
use Illuminate\Http\Response;

class LoadUnloadSheetController extends Controller
{
    public function __construct(private LoadUnloadSheetService $sheet) {}

    public function pdf(LoadUnloadSheetPdfRequest $request): Response
    {
        return $this->sheet->pdf($request->validated());
    }
}
