<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\StudentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', [HealthController::class, 'show'])->name('api.health');

    Route::get('/students/search', [StudentController::class, 'search'])->name('api.students.search');
    Route::apiResource('students', StudentController::class)->only(['index', 'show', 'store', 'update'])->names([
        'index' => 'api.students.index',
        'show' => 'api.students.show',
        'store' => 'api.students.store',
        'update' => 'api.students.update',
    ]);
});
