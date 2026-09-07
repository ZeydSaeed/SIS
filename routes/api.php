<?php

use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\StudentController;
use App\Security\Middleware\RequireSchoolContextMiddleware;
use App\Security\Middleware\SchoolContextMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', [HealthController::class, 'show'])->name('api.health');

    Route::middleware([
        'auth:sanctum',
        SchoolContextMiddleware::class,
        RequireSchoolContextMiddleware::class,
        'throttle:api-students',
    ])->group(function (): void {
        Route::get('/students/search', [StudentController::class, 'search'])
            ->middleware('throttle:api-search')
            ->name('api.students.search');

        Route::apiResource('students', StudentController::class)->only(['index', 'show', 'store', 'update'])->names([
            'index' => 'api.students.index',
            'show' => 'api.students.show',
            'store' => 'api.students.store',
            'update' => 'api.students.update',
        ]);

        Route::get('/security/admin-probe', function (Request $request) {
            abort_unless(Gate::allows('security.manage_users'), 403);

            return response()->json(['status' => 'ok']);
        })->name('api.security.admin_probe');

        Route::post('enrollments/{enrollment}/cancel', [EnrollmentController::class, 'cancel'])
            ->name('api.enrollments.cancel');

        Route::apiResource('enrollments', EnrollmentController::class)->only(['index', 'show', 'store', 'update'])->names([
            'index' => 'api.enrollments.index',
            'show' => 'api.enrollments.show',
            'store' => 'api.enrollments.store',
            'update' => 'api.enrollments.update',
        ]);
    });
});
