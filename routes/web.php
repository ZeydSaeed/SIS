<?php

use App\Http\Controllers\Admission\AdmissionPageController;
use App\Http\Controllers\Attendance\AttendancePageController;
use App\Http\Controllers\Enrollment\EnrollmentPageController;
use App\Http\Controllers\Exams\ExamPageController;
use App\Http\Controllers\Grades\GradesPageController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Intelligence\RecommendationController;
use App\Http\Controllers\Ops\OpsModulePageController;
use App\Http\Controllers\Reports\ReportsPageController;
use App\Http\Controllers\Results\ResultsPageController;
use App\Http\Controllers\SchoolContextController;
use App\Http\Controllers\Student\StudentPageController;
use App\Http\Controllers\Teachers\TeacherPageController;
use App\Http\Controllers\Timetable\TimetablePageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Legacy /hub bookmarks → dashboard only (no guest workspace).
    Route::get('/hub', function (Request $request) {
        $desktop = $request->boolean('desktop') || $request->boolean('shell');

        return redirect($desktop ? '/dashboard?desktop=1' : route('dashboard'));
    })->name('hub');

    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::post('/context/school', [SchoolContextController::class, 'updateSchool'])->name('context.school');
    Route::post('/context/academic-year', [SchoolContextController::class, 'updateYear'])->name('context.academic-year');
    Route::post('/context/ops-bootstrap', [SchoolContextController::class, 'bootstrapOps'])->name('context.ops-bootstrap');

    Route::prefix('students')->name('students.')->middleware('require.school.context')->group(function (): void {
        Route::get('/', [StudentPageController::class, 'index'])->name('index');
        Route::post('/bulk-status', [StudentPageController::class, 'bulkStatus'])->name('bulk-status');
        Route::put('/{student}', [StudentPageController::class, 'update'])->whereNumber('student')->name('update');
        Route::get('/{student}', [StudentPageController::class, 'show'])->name('show');
    });

    Route::prefix('admission')->name('admission.')->middleware('require.school.context')->group(function (): void {
        Route::get('/', [AdmissionPageController::class, 'index'])->name('index');
        Route::get('/drafts', [AdmissionPageController::class, 'drafts'])->name('drafts');
        Route::get('/submitted', [AdmissionPageController::class, 'submitted'])->name('submitted');
        Route::get('/under-review', [AdmissionPageController::class, 'underReview'])->name('under-review');
        Route::get('/interview', [AdmissionPageController::class, 'interview'])->name('interview');
        Route::get('/waitlisted', [AdmissionPageController::class, 'waitlisted'])->name('waitlisted');
        Route::get('/accepted', [AdmissionPageController::class, 'accepted'])->name('accepted');
        Route::get('/converted', [AdmissionPageController::class, 'converted'])->name('converted');
        Route::post('/periods', [AdmissionPageController::class, 'storePeriod'])->name('periods.store');
        Route::put('/periods/{period}', [AdmissionPageController::class, 'updatePeriod'])
            ->whereNumber('period')
            ->name('periods.update');
        Route::patch('/periods/{period}/status', [AdmissionPageController::class, 'changePeriodStatus'])
            ->whereNumber('period')
            ->name('periods.status');
        Route::post('/periods/{period}/archive', [AdmissionPageController::class, 'archivePeriod'])
            ->whereNumber('period')
            ->name('periods.archive');
        Route::post('/applications', [AdmissionPageController::class, 'storeApplication'])->name('applications.store');
        Route::put('/applications/{application}', [AdmissionPageController::class, 'updateApplication'])
            ->whereNumber('application')
            ->name('applications.update');
        Route::post('/applications/bulk-transition', [AdmissionPageController::class, 'bulkTransition'])
            ->name('applications.bulk-transition');
        Route::post('/applications/{application}/transition', [AdmissionPageController::class, 'transition'])
            ->whereNumber('application')
            ->name('applications.transition');
        Route::post('/applications/{application}/convert', [AdmissionPageController::class, 'convert'])
            ->whereNumber('application')
            ->name('applications.convert');
        Route::post('/applications/{application}/documents', [AdmissionPageController::class, 'storeDocument'])
            ->whereNumber('application')
            ->name('applications.documents.store');
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

    // Blueprint lifecycle / support shells (UI scaffolding — handlers land per module gate).
    Route::middleware('require.school.context')->group(function (): void {
        Route::get('/guardians', [OpsModulePageController::class, 'guardians'])->name('guardians.index');
        Route::get('/curriculum', [OpsModulePageController::class, 'curriculum'])->name('curriculum.index');
        Route::get('/promotion', [OpsModulePageController::class, 'promotion'])->name('promotion.index');
        Route::get('/transfers', [OpsModulePageController::class, 'transfers'])->name('transfers.index');
        Route::get('/graduation', [OpsModulePageController::class, 'graduation'])->name('graduation.index');
        Route::get('/certificates', [OpsModulePageController::class, 'certificates'])->name('certificates.index');
        Route::get('/finance', [OpsModulePageController::class, 'finance'])->name('finance.index');
        Route::get('/holidays', [OpsModulePageController::class, 'holidays'])->name('holidays.index');
        Route::get('/health', [OpsModulePageController::class, 'health'])->name('health.index');
        Route::get('/hr', [OpsModulePageController::class, 'hr'])->name('hr.index');
        Route::get('/documents', [OpsModulePageController::class, 'documents'])->name('documents.index');
        Route::get('/communication', [OpsModulePageController::class, 'communication'])->name('communication.index');
        Route::get('/workflow', [OpsModulePageController::class, 'workflow'])->name('workflow.index');
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
