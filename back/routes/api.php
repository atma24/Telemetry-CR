<?php

use App\Http\Controllers\Api\Apbd\AdminApbdController;
use App\Http\Controllers\Api\Apbd\ApbdController;
use App\Http\Controllers\Api\Auth\AdminTokenController;
use App\Http\Controllers\Api\Auth\AuthenticatedUserController;
use App\Http\Controllers\Api\Dashboard\DashboardController;
use App\Http\Controllers\Api\Dataset\DatasetController;
use App\Http\Controllers\Api\Fasilitas\Admin\AdminFasilitasController;
use App\Http\Controllers\Api\Fasilitas\FasilitasController;
use App\Http\Controllers\Api\Feedback\FeedbackController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\Wilayah\Admin\AdminWilayahController;
use App\Http\Controllers\Api\Wilayah\WilayahController;
use Illuminate\Support\Facades\Route;

Route::name('api.')->group(function (): void {
    Route::get('health', HealthController::class)->name('health');

    Route::middleware('auth:sanctum')->get('user', AuthenticatedUserController::class)
        ->name('user');

    Route::post('admin/login', [AdminTokenController::class, 'store'])
        ->middleware('throttle:5,1')->name('admin.login');
    Route::delete('admin/token', [AdminTokenController::class, 'destroy'])
        ->middleware('auth:sanctum')->name('admin.token.destroy');

    Route::get('regions', [WilayahController::class, 'index'])->name('regions.index');
    Route::get('regions/{region}', [WilayahController::class, 'show'])
        ->whereUuid('region')
        ->name('regions.show');

    Route::get('facilities', [FasilitasController::class, 'index'])->name('facilities.index');
    Route::get('facilities/{facility}', [FasilitasController::class, 'show'])
        ->whereUuid('facility')->name('facilities.show');
    Route::get('apbd', [ApbdController::class, 'index'])->name('apbd.index');
    Route::get('apbd/{apbd}', [ApbdController::class, 'show'])
        ->whereUuid('apbd')->name('apbd.show');
    Route::get('datasets', [DatasetController::class, 'index'])->name('datasets.index');
    Route::get('datasets/{dataset}', [DatasetController::class, 'show'])
        ->whereUuid('dataset')->name('datasets.show');
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('auth:sanctum')->post('feedback', [FeedbackController::class, 'store'])
        ->name('feedback.store');

    Route::prefix('admin')->name('admin.')->middleware(['auth:sanctum', 'can:admin'])->group(function (): void {
        Route::get('facilities', [AdminFasilitasController::class, 'index'])->name('facilities.index');
        Route::post('facilities', [AdminFasilitasController::class, 'store'])->name('facilities.store');
        Route::post('facilities/import', [AdminFasilitasController::class, 'import'])->middleware('throttle:10,1')->name('facilities.import');
        Route::get('facilities/imports/{importJob}', [AdminFasilitasController::class, 'showImport'])
            ->whereUuid('importJob')->name('facilities.imports.show');
        Route::get('facilities/imports/{importJob}/errors', [AdminFasilitasController::class, 'downloadImportErrors'])
            ->whereUuid('importJob')->name('facilities.imports.errors');
        Route::get('facilities/{facility}', [AdminFasilitasController::class, 'show'])
            ->whereUuid('facility')->name('facilities.show');
        Route::put('facilities/{facility}', [AdminFasilitasController::class, 'update'])
            ->whereUuid('facility')->name('facilities.update');
        Route::patch('facilities/{facility}', [AdminFasilitasController::class, 'update'])
            ->whereUuid('facility')->name('facilities.patch');
        Route::post('facilities/{facility}/versions/{version}/validate', [AdminFasilitasController::class, 'validateVersion'])
            ->whereUuid('facility')->whereUuid('version')->name('facilities.versions.validate');
        Route::post('facilities/{facility}/versions/{version}/publish', [AdminFasilitasController::class, 'publish'])
            ->whereUuid('facility')->whereUuid('version')->name('facilities.versions.publish');
        Route::post('facilities/{facility}/archive', [AdminFasilitasController::class, 'archive'])
            ->whereUuid('facility')->name('facilities.archive');

        Route::get('apbd', [AdminApbdController::class, 'index'])->name('apbd.index');
        Route::post('apbd', [AdminApbdController::class, 'store'])->name('apbd.store');
        Route::post('apbd/import', [AdminApbdController::class, 'import'])->middleware('throttle:10,1')->name('apbd.import');
        Route::get('apbd/imports/{importJob}', [AdminApbdController::class, 'showImport'])
            ->whereUuid('importJob')->name('apbd.imports.show');
        Route::get('apbd/imports/{importJob}/errors', [AdminApbdController::class, 'downloadErrors'])
            ->whereUuid('importJob')->name('apbd.imports.errors');
        Route::get('apbd/{apbd}', [AdminApbdController::class, 'show'])->whereUuid('apbd')->name('apbd.show');
        Route::put('apbd/{apbd}', [AdminApbdController::class, 'update'])->whereUuid('apbd')->name('apbd.update');
        Route::patch('apbd/{apbd}', [AdminApbdController::class, 'update'])->whereUuid('apbd')->name('apbd.patch');
        Route::delete('apbd/{apbd}', [AdminApbdController::class, 'destroy'])->whereUuid('apbd')->name('apbd.destroy');
        Route::post('apbd/{apbd}/validate', [AdminApbdController::class, 'validateRecord'])->whereUuid('apbd')->name('apbd.validate');
        Route::post('apbd/{apbd}/publish', [AdminApbdController::class, 'publish'])->whereUuid('apbd')->name('apbd.publish');

        Route::get('regions', [AdminWilayahController::class, 'index'])->name('regions.index');
        Route::post('regions/import', [AdminWilayahController::class, 'import'])->middleware('throttle:10,1')->name('regions.import');
        Route::get('regions/imports/{importJob}', [AdminWilayahController::class, 'showImport'])
            ->whereUuid('importJob')->name('regions.imports.show');
        Route::get('regions/imports/{importJob}/errors', [AdminWilayahController::class, 'downloadImportErrors'])
            ->whereUuid('importJob')->name('regions.imports.errors');
        Route::post('regions', [AdminWilayahController::class, 'store'])->name('regions.store');
        Route::get('regions/{region}', [AdminWilayahController::class, 'show'])->whereUuid('region')->name('regions.show');
        Route::put('regions/{region}', [AdminWilayahController::class, 'update'])->whereUuid('region')->name('regions.update');
        Route::patch('regions/{region}', [AdminWilayahController::class, 'update'])->whereUuid('region')->name('regions.patch');
        Route::post('regions/{region}/versions/{version}/validate', [AdminWilayahController::class, 'validateVersion'])
            ->whereUuid('region')->whereUuid('version')->name('regions.versions.validate');
        Route::post('regions/{region}/versions/{version}/publish', [AdminWilayahController::class, 'publish'])
            ->whereUuid('region')->whereUuid('version')->name('regions.versions.publish');
        Route::post('regions/{region}/archive', [AdminWilayahController::class, 'archive'])
            ->whereUuid('region')->name('regions.archive');
        Route::get('feedback', [FeedbackController::class, 'index'])->name('feedback.index');
        Route::patch('feedback/{feedback}/status', [FeedbackController::class, 'updateStatus'])
            ->whereUuid('feedback')->name('feedback.status');
    });
});
