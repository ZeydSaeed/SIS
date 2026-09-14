<?php

use App\Http\Controllers\Attendance\AttendancePageController;
use App\Http\Controllers\Enrollment\EnrollmentPageController;
use App\Http\Controllers\Intelligence\RecommendationController;
use App\Http\Controllers\Results\ResultsPageController;
use App\Http\Controllers\Student\StudentPageController;
use App\Http\Controllers\Teachers\TeacherPageController;
use App\Http\Controllers\Timetable\TimetablePageController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::prefix('students')->name('students.')->middleware('require.school.context')->group(function (): void {
        Route::get('/', [StudentPageController::class, 'index'])->name('index');
        Route::get('/{student}', [StudentPageController::class, 'show'])->name('show');
    });

    Route::prefix('enrollments')->name('enrollments.')->middleware('require.school.context')->group(function (): void {
        Route::get('/', [EnrollmentPageController::class, 'index'])->name('index');
    });

    Route::prefix('attendance')->name('attendance.')->middleware('require.school.context')->group(function (): void {
        Route::get('/', [AttendancePageController::class, 'index'])->name('index');
    });

    Route::prefix('teachers')->name('teachers.')->middleware('require.school.context')->group(function (): void {
        Route::get('/', [TeacherPageController::class, 'index'])->name('index');
    });

    Route::prefix('timetable')->name('timetable.')->middleware('require.school.context')->group(function (): void {
        Route::get('/', [TimetablePageController::class, 'index'])->name('index');
    });

    Route::prefix('results')->name('results.')->middleware('require.school.context')->group(function (): void {
        Route::get('/', [ResultsPageController::class, 'index'])->name('index');
        Route::get('/show', [ResultsPageController::class, 'show'])->name('show');
        Route::get('/term', [ResultsPageController::class, 'term'])->name('term');
    });

    Route::prefix('intelligence')->name('intelligence.')->group(function () {
        Route::get('recommendations', [RecommendationController::class, 'index'])
            ->name('recommendations.index');
        Route::get('recommendations/{recommendation}', [RecommendationController::class, 'show'])
            ->name('recommendations.show');
        Route::post('recommendations/{recommendation}/approve', [RecommendationController::class, 'approve'])
            ->name('recommendations.approve');
        Route::post('recommendations/{recommendation}/reject', [RecommendationController::class, 'reject'])
            ->name('recommendations.reject');
    });
});

require __DIR__.'/settings.php';
