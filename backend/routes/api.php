<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BarcodeScannerController;
use App\Http\Controllers\EirSigningController;
use Illuminate\Support\Facades\Route;

Route::get('/login-context', [AuthController::class, 'context']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::get('/barcode-scanner/lookups', [BarcodeScannerController::class, 'lookups']);
    Route::post('/barcode-scanner/scan', [BarcodeScannerController::class, 'scan']);
    Route::post('/barcode-scanner/save', [BarcodeScannerController::class, 'save']);

    // The legacy scanner had no menu and no pending-signature list. Any
    // logged-in user can open this list. Gating it on the office EIR program
    // returned 403 for User accounts, which the screen shows as
    // "Unable to load records."
    Route::get('/eir-signing', [EirSigningController::class, 'index']);
    Route::get('/eir-signing/{eir_form}', [EirSigningController::class, 'show']);
    Route::post('/eir-signing/{eir_form}/signature', [EirSigningController::class, 'store']);
});
