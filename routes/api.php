<?php

declare(strict_types=1);

use App\Http\Controllers\Api\GeoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Geo ma'lumotnomasi — ochiq (davlat → viloyat → tuman kaskadi).
Route::prefix('geo')->group(function (): void {
    Route::get('countries', [GeoController::class, 'countries']);
    Route::get('countries/{country}/regions', [GeoController::class, 'regions']);
    Route::get('regions/{region}/districts', [GeoController::class, 'districts']);
});
