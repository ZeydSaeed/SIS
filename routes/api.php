<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\GradeController;
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

        Route::post('grades', [GradeController::class, 'store'])->name('api.grades.store');
        Route::get('grades/{grade}', [GradeController::class, 'show'])->name('api.grades.show');
        Route::post('grades/{grade}/correct', [GradeController::class, 'correct'])->name('api.grades.correct');
        Route::post('grades/{grade}/void', [GradeController::class, 'void'])->name('api.grades.void');
        Route::post('grades/{grade}/finalize', [GradeController::class, 'finalize'])->name('api.grades.finalize');
        Route::get('exam-sessions/{examSession}/grades', [GradeController::class, 'forExamSession'])
            ->name('api.exam_sessions.grades');
        Route::get('enrollments/{enrollment}/grades', [GradeController::class, 'forEnrollment'])
            ->name('api.enrollments.grades');
        Route::get('exam-enrollments/{examEnrollment}/current-grade', [GradeController::class, 'currentForExamEnrollment'])
            ->name('api.exam_enrollments.current_grade');

        Route::post('attendance/sessions', [AttendanceController::class, 'storeSession'])
            ->name('api.attendance.sessions.store');
        Route::get('attendance/sessions', [AttendanceController::class, 'indexSessions'])
            ->name('api.attendance.sessions.index');
        Route::get('attendance/sessions/{session}', [AttendanceController::class, 'showSession'])
            ->name('api.attendance.sessions.show');
        Route::post('attendance/sessions/{session}/marks', [AttendanceController::class, 'mark'])
            ->name('api.attendance.sessions.marks');
        Route::post('attendance/sessions/{session}/students/{student}/correct', [AttendanceController::class, 'correct'])
            ->name('api.attendance.sessions.correct');
        Route::post('attendance/sessions/{session}/close', [AttendanceController::class, 'close'])
            ->name('api.attendance.sessions.close');
        Route::post('attendance/sessions/{session}/cancel', [AttendanceController::class, 'cancel'])
            ->name('api.attendance.sessions.cancel');
        Route::get('attendance/sections/{section}', [AttendanceController::class, 'sectionAttendance'])
            ->name('api.attendance.sections.show');
        Route::get('attendance/sections/{section}/daily-summary', [AttendanceController::class, 'dailySummary'])
            ->name('api.attendance.sections.daily_summary');
        Route::get('attendance/students/{student}', [AttendanceController::class, 'studentAttendance'])
            ->name('api.attendance.students.show');
    });
});
