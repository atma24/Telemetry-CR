<?php

use App\Http\Controllers\Api\Admin\RegionController as AdminRegionController;
use App\Http\Controllers\Api\Auth\AuthenticatedUserController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\Regions\RegionController;
use Illuminate\Support\Facades\Route;

Route::name('api.')->group(function (): void {
    Route::get('health', HealthController::class)->name('health');

    Route::middleware('auth:sanctum')->get('user', AuthenticatedUserController::class)
        ->name('user');

    Route::get('regions', [RegionController::class, 'index'])->name('regions.index');
    Route::get('regions/{region}', [RegionController::class, 'show'])
        ->whereUuid('region')
        ->name('regions.show');

    Route::prefix('admin')->name('admin.')->middleware(['auth:sanctum', 'can:admin'])->group(function (): void {
        Route::get('regions', [AdminRegionController::class, 'index'])->name('regions.index');
        Route::post('regions', [AdminRegionController::class, 'store'])->name('regions.store');
        Route::get('regions/{region}', [AdminRegionController::class, 'show'])->whereUuid('region')->name('regions.show');
        Route::put('regions/{region}', [AdminRegionController::class, 'update'])->whereUuid('region')->name('regions.update');
        Route::patch('regions/{region}', [AdminRegionController::class, 'update'])->whereUuid('region')->name('regions.patch');
        Route::post('regions/{region}/versions/{version}/validate', [AdminRegionController::class, 'validateVersion'])
            ->whereUuid('region')->whereUuid('version')->name('regions.versions.validate');
        Route::post('regions/{region}/versions/{version}/publish', [AdminRegionController::class, 'publish'])
            ->whereUuid('region')->whereUuid('version')->name('regions.versions.publish');
        Route::post('regions/{region}/archive', [AdminRegionController::class, 'archive'])
            ->whereUuid('region')->name('regions.archive');
    });
});
