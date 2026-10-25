<?php

use App\Http\Controllers\Api\Auth\AuthenticatedUserController;
use App\Http\Controllers\Api\Regions\RegionController;
use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('health', HealthController::class)->name('health');

    Route::middleware('auth:sanctum')->get('user', AuthenticatedUserController::class)
        ->name('user');

    Route::get('regions', [RegionController::class, 'index'])->name('regions.index');
    Route::get('regions/{region}', [RegionController::class, 'show'])
        ->whereUuid('region')
        ->name('regions.show');
});
