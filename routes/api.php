<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\GradeController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\ResultsController;
use App\Http\Controllers\Api\ResultsWriteController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\VocationalController;
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

        Route::post('timetable/schedules', [ScheduleController::class, 'store'])
            ->name('api.timetable.schedules.store');
        Route::get('timetable/schedules', [ScheduleController::class, 'index'])
            ->name('api.timetable.schedules.index');
        Route::get('timetable/schedules/{schedule}', [ScheduleController::class, 'show'])
            ->name('api.timetable.schedules.show');
        Route::patch('timetable/schedules/{schedule}', [ScheduleController::class, 'update'])
            ->name('api.timetable.schedules.update');
        Route::post('timetable/schedules/{schedule}/cancel', [ScheduleController::class, 'cancel'])
            ->name('api.timetable.schedules.cancel');
        Route::post('timetable/schedules/{schedule}/exceptions', [ScheduleController::class, 'storeException'])
            ->name('api.timetable.schedules.exceptions.store');
        Route::patch('timetable/schedule-exceptions/{exception}', [ScheduleController::class, 'updateException'])
            ->name('api.timetable.schedule_exceptions.update');

        Route::get('results/term', [ResultsController::class, 'officialTerm'])
            ->name('api.results.term.show');
        Route::get('results/annual', [ResultsController::class, 'officialAnnual'])
            ->name('api.results.annual.show');
        Route::get('results/gpa', [ResultsController::class, 'officialYearGpa'])
            ->name('api.results.gpa.show');
        Route::get('results/ranking', [ResultsController::class, 'currentRanking'])
            ->name('api.results.ranking.show');
        Route::get('results/transcripts/issued', [ResultsController::class, 'issuedTranscript'])
            ->name('api.results.transcripts.issued');

        Route::post('results/term/calculate', [ResultsWriteController::class, 'calculateTerm'])
            ->name('api.results.term.calculate');
        Route::post('results/term/finalize', [ResultsWriteController::class, 'finalizeTerm'])
            ->name('api.results.term.finalize');
        Route::post('results/annual/calculate', [ResultsWriteController::class, 'calculateAnnual'])
            ->name('api.results.annual.calculate');
        Route::post('results/annual/finalize', [ResultsWriteController::class, 'finalizeAnnual'])
            ->name('api.results.annual.finalize');
        Route::post('results/gpa/calculate', [ResultsWriteController::class, 'calculateGpa'])
            ->name('api.results.gpa.calculate');
        Route::post('results/gpa/finalize', [ResultsWriteController::class, 'finalizeGpa'])
            ->name('api.results.gpa.finalize');
        Route::post('results/ranking/build', [ResultsWriteController::class, 'buildRanking'])
            ->name('api.results.ranking.build');
        Route::post('results/transcripts/issue', [ResultsWriteController::class, 'issueTranscript'])
            ->name('api.results.transcripts.issue');
        Route::post('results/term/rebuild', [ResultsWriteController::class, 'rebuildTerm'])
            ->name('api.results.term.rebuild');
        Route::post('results/annual/rebuild', [ResultsWriteController::class, 'rebuildAnnual'])
            ->name('api.results.annual.rebuild');
        Route::post('results/gpa/rebuild', [ResultsWriteController::class, 'rebuildGpa'])
            ->name('api.results.gpa.rebuild');

        Route::post('vocational/specializations', [VocationalController::class, 'storeSpecialization'])
            ->name('api.vocational.specializations.store');
        Route::get('vocational/specializations', [VocationalController::class, 'indexSpecializations'])
            ->name('api.vocational.specializations.index');
        Route::get('vocational/specializations/{specialization}', [VocationalController::class, 'showSpecialization'])
            ->name('api.vocational.specializations.show');
        Route::patch('vocational/specializations/{specialization}', [VocationalController::class, 'updateSpecialization'])
            ->name('api.vocational.specializations.update');
        Route::post('vocational/specializations/{specialization}/deactivate', [VocationalController::class, 'deactivateSpecialization'])
            ->name('api.vocational.specializations.deactivate');
        Route::post('vocational/specializations/{specialization}/tracks', [VocationalController::class, 'storeTrack'])
            ->name('api.vocational.tracks.store');
        Route::patch('vocational/tracks/{track}', [VocationalController::class, 'updateTrack'])
            ->name('api.vocational.tracks.update');
        Route::post('vocational/tracks/{track}/deactivate', [VocationalController::class, 'deactivateTrack'])
            ->name('api.vocational.tracks.deactivate');
        Route::post('vocational/specializations/{specialization}/subjects', [VocationalController::class, 'linkSubject'])
            ->name('api.vocational.specialization_subjects.store');
        Route::post('vocational/specialization-subjects/{link}/deactivate', [VocationalController::class, 'deactivateSubjectLink'])
            ->name('api.vocational.specialization_subjects.deactivate');
    });
});
