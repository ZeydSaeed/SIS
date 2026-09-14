<?php

use App\Http\Controllers\Attendance\AttendancePageController;
use App\Http\Controllers\Enrollment\EnrollmentPageController;
use App\Http\Controllers\Exams\ExamPageController;
use App\Http\Controllers\Grades\GradesPageController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Intelligence\RecommendationController;
use App\Http\Controllers\Reports\ReportsPageController;
use App\Http\Controllers\Results\ResultsPageController;
use App\Http\Controllers\SchoolContextController;
use App\Http\Controllers\Student\StudentPageController;
use App\Http\Controllers\Teachers\TeacherPageController;
use App\Http\Controllers\Timetable\TimetablePageController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::inertia('/hub', 'hub')->name('hub');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::post('/context/school', [SchoolContextController::class, 'updateSchool'])->name('context.school');
    Route::post('/context/academic-year', [SchoolContextController::class, 'updateYear'])->name('context.academic-year');

    Route::prefix('students')->name('students.')->middleware('require.school.context')->group(function (): void {
        Route::get('/', [StudentPageController::class, 'index'])->name('index');
        Route::get('/{student}', [StudentPageController::class, 'show'])->name('show');
    });

    Route::prefix('enrollments')->name('enrollments.')->middleware('require.school.context')->group(function (): void {
        Route::get('/', [EnrollmentPageController::class, 'index'])->name('index');
        Route::get('/create', [EnrollmentPageController::class, 'create'])->name('create');
        Route::post('/', [EnrollmentPageController::class, 'store'])->name('store');
        Route::get('/{enrollment}', [EnrollmentPageController::class, 'show'])->name('show');
        Route::get('/{enrollment}/edit', [EnrollmentPageController::class, 'edit'])->name('edit');
        Route::put('/{enrollment}', [EnrollmentPageController::class, 'update'])->name('update');
    });

    Route::prefix('attendance')->name('attendance.')->middleware('require.school.context')->group(function (): void {
        Route::get('/', [AttendancePageController::class, 'index'])->name('index');
        Route::get('/create', [AttendancePageController::class, 'create'])->name('create');
        Route::post('/', [AttendancePageController::class, 'store'])->name('store');
        Route::get('/{session}', [AttendancePageController::class, 'show'])->name('show');
        Route::get('/{session}/mark', [AttendancePageController::class, 'markForm'])->name('mark');
        Route::post('/{session}/mark', [AttendancePageController::class, 'markStore'])->name('mark.store');
        Route::post('/{session}/close', [AttendancePageController::class, 'close'])->name('close');
    });

    Route::prefix('teachers')->name('teachers.')->middleware('require.school.context')->group(function (): void {
        Route::get('/', [TeacherPageController::class, 'index'])->name('index');
        Route::get('/{teacher}', [TeacherPageController::class, 'show'])->whereNumber('teacher')->name('show');
    });

    Route::prefix('timetable')->name('timetable.')->middleware('require.school.context')->group(function (): void {
        Route::get('/', [TimetablePageController::class, 'index'])->name('index');
        Route::get('/{schedule}', [TimetablePageController::class, 'show'])->whereNumber('schedule')->name('show');
    });

    Route::prefix('results')->name('results.')->middleware('require.school.context')->group(function (): void {
        Route::get('/', [ResultsPageController::class, 'index'])->name('index');
        Route::get('/show', [ResultsPageController::class, 'show'])->name('show');
        Route::get('/term', [ResultsPageController::class, 'term'])->name('term');
        Route::get('/transcript', [ResultsPageController::class, 'transcript'])->name('transcript');
    });

    Route::prefix('exams')->name('exams.')->middleware('require.school.context')->group(function (): void {
        Route::get('/', [ExamPageController::class, 'index'])->name('index');
        Route::get('/{exam}', [ExamPageController::class, 'show'])->name('show');
    });

    Route::prefix('grades')->name('grades.')->middleware('require.school.context')->group(function (): void {
        Route::get('/', [GradesPageController::class, 'index'])->name('index');
        Route::get('/enter', [GradesPageController::class, 'enterForm'])->name('enter');
        Route::post('/', [GradesPageController::class, 'store'])->name('store');
        Route::get('/actions', [GradesPageController::class, 'actions'])->name('actions');
        Route::post('/{grade}/correct', [GradesPageController::class, 'correct'])->name('correct');
        Route::post('/{grade}/void', [GradesPageController::class, 'void'])->name('void');
        Route::post('/{grade}/finalize', [GradesPageController::class, 'finalize'])->name('finalize');
    });

    Route::prefix('reports')->name('reports.')->middleware('require.school.context')->group(function (): void {
        Route::get('/', [ReportsPageController::class, 'index'])->name('index');
        Route::get('/attendance-daily', [ReportsPageController::class, 'attendanceDaily'])->name('attendance-daily');
        Route::get('/enrollment-roster', [ReportsPageController::class, 'enrollmentRoster'])->name('enrollment-roster');
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
