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

    // Signing is the EIR module's gate-side workflow, so it follows the same
    // menu assignment webmoreta uses for view_equip_interchange_receipt.php.
    // Barcode scanning stays open to any logged-in user, matching the legacy
    // scanner, which had no menu for it.
    Route::middleware('menu:view_equip_interchange_receipt.php,view')->group(function () {
        Route::get('/eir-signing', [EirSigningController::class, 'index']);
        Route::get('/eir-signing/{eir_form}', [EirSigningController::class, 'show']);
        Route::post('/eir-signing/{eir_form}/signature', [EirSigningController::class, 'store']);
    });
});
