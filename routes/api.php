<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\GradeController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MetricsController;
use App\Http\Controllers\Api\TeacherController;
use App\Http\Controllers\Api\PortalResultsController;
use App\Http\Controllers\Api\PortalScopesController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\ResultsController;
use App\Http\Controllers\Api\ApprovalFlowController;
use App\Http\Controllers\Api\ApprovalRequestController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\FeeTypeController;
use App\Http\Controllers\Api\FinanceTransactionController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\NotificationTemplateController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\StudentFeeController;
use App\Http\Controllers\Api\TransferController;
use App\Http\Controllers\Api\ResultsWriteController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\HrController;
use App\Http\Controllers\Api\VocationalController;
use App\Security\Middleware\RequireSchoolContextMiddleware;
use App\Security\Middleware\SchoolContextMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', [HealthController::class, 'show'])->name('api.health');
    Route::get('/metrics', [MetricsController::class, 'show'])
        ->middleware(\App\Http\Middleware\MetricsTokenMiddleware::class)
        ->name('api.metrics');

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
        Route::get('timetable/schedules/{schedule}/exceptions', [ScheduleController::class, 'indexExceptionsForSchedule'])
            ->name('api.timetable.schedules.exceptions.index');
        Route::get('timetable/schedule-exceptions', [ScheduleController::class, 'indexExceptions'])
            ->name('api.timetable.schedule_exceptions.index');
        Route::get('timetable/schedule-exceptions/{exception}', [ScheduleController::class, 'showException'])
            ->name('api.timetable.schedule_exceptions.show');
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

        Route::get('portal/results/term', [PortalResultsController::class, 'officialTerm'])
            ->name('api.portal.results.term.show');
        Route::get('portal/results/annual', [PortalResultsController::class, 'officialAnnual'])
            ->name('api.portal.results.annual.show');
        Route::get('portal/results/gpa', [PortalResultsController::class, 'officialYearGpa'])
            ->name('api.portal.results.gpa.show');
        Route::get('portal/results/transcripts/issued', [PortalResultsController::class, 'issuedTranscript'])
            ->name('api.portal.results.transcripts.issued');

        Route::post('portal/scopes', [PortalScopesController::class, 'store'])
            ->name('api.portal.scopes.store');
        Route::delete('portal/scopes', [PortalScopesController::class, 'destroy'])
            ->name('api.portal.scopes.destroy');
        Route::get('portal/scopes', [PortalScopesController::class, 'index'])
            ->name('api.portal.scopes.index');

        Route::post('teachers', [TeacherController::class, 'store'])
            ->name('api.teachers.store');
        Route::get('teachers', [TeacherController::class, 'index'])
            ->name('api.teachers.index');
        Route::get('teachers/{teacher}', [TeacherController::class, 'show'])
            ->name('api.teachers.show');
        Route::patch('teachers/{teacher}', [TeacherController::class, 'update'])
            ->name('api.teachers.update');
        Route::post('teachers/{teacher}/deactivate', [TeacherController::class, 'deactivate'])
            ->name('api.teachers.deactivate');
        Route::post('teachers/{teacher}/subjects', [TeacherController::class, 'assignSubject'])
            ->name('api.teachers.subjects.assign');
        Route::delete('teachers/{teacher}/subjects', [TeacherController::class, 'unlinkSubject'])
            ->name('api.teachers.subjects.unlink');
        Route::post('teachers/{teacher}/qualifications', [TeacherController::class, 'storeQualification'])
            ->name('api.teachers.qualifications.store');
        Route::get('teachers/{teacher}/qualifications', [TeacherController::class, 'indexQualifications'])
            ->name('api.teachers.qualifications.index');
        Route::post('teachers/{teacher}/qualifications/{qualification}/void', [TeacherController::class, 'voidQualification'])
            ->whereNumber('teacher')
            ->whereNumber('qualification')
            ->name('api.teachers.qualifications.void');
        Route::post('teachers/{teacher}/qualifications/{qualification}/attach-document', [TeacherController::class, 'attachQualificationDocument'])
            ->whereNumber('teacher')
            ->whereNumber('qualification')
            ->name('api.teachers.qualifications.attach-document');

        Route::post('promotion/rules', [PromotionController::class, 'storeRule'])
            ->name('api.promotion.rules.store');
        Route::get('promotion/rules', [PromotionController::class, 'indexRules'])
            ->name('api.promotion.rules.index');
        Route::post('promotion/records', [PromotionController::class, 'storeRecord'])
            ->name('api.promotion.records.store');
        Route::get('promotion/records', [PromotionController::class, 'indexRecords'])
            ->name('api.promotion.records.index');

        Route::post('transfers/requests', [TransferController::class, 'store'])
            ->name('api.transfers.requests.store');
        Route::get('transfers/requests', [TransferController::class, 'index'])
            ->name('api.transfers.requests.index');
        Route::post('transfers/requests/{transferRequest}/approve', [TransferController::class, 'approve'])
            ->name('api.transfers.requests.approve');
        Route::post('transfers/requests/{transferRequest}/reject', [TransferController::class, 'reject'])
            ->name('api.transfers.requests.reject');
        Route::post('transfers/requests/{transferRequest}/complete', [TransferController::class, 'complete'])
            ->name('api.transfers.requests.complete');
        Route::post('transfers/requests/{transferRequest}/cancel', [TransferController::class, 'cancel'])
            ->name('api.transfers.requests.cancel');

        Route::post('documents', [DocumentController::class, 'store'])
            ->name('api.documents.store');
        Route::post('documents/upload', [DocumentController::class, 'upload'])
            ->name('api.documents.upload');
        Route::get('documents/{document}/content', [DocumentController::class, 'download'])
            ->whereNumber('document')
            ->name('api.documents.download');
        Route::get('documents', [DocumentController::class, 'index'])
            ->name('api.documents.index');

        Route::post('finance/fee-types', [FeeTypeController::class, 'store'])
            ->name('api.finance.fee-types.store');
        Route::get('finance/fee-types', [FeeTypeController::class, 'index'])
            ->name('api.finance.fee-types.index');
        Route::post('finance/student-fees', [StudentFeeController::class, 'store'])
            ->name('api.finance.student-fees.store');
        Route::get('finance/student-fees', [StudentFeeController::class, 'index'])
            ->name('api.finance.student-fees.index');
        Route::post('finance/payments', [PaymentController::class, 'store'])
            ->name('api.finance.payments.store');
        Route::get('finance/payments', [PaymentController::class, 'index'])
            ->name('api.finance.payments.index');
        Route::post('finance/payments/{payment}/void', [PaymentController::class, 'void'])
            ->whereNumber('payment')
            ->name('api.finance.payments.void');
        Route::get('finance/transactions', [FinanceTransactionController::class, 'index'])
            ->name('api.finance.transactions.index');

        Route::post('communication/templates', [NotificationTemplateController::class, 'store'])
            ->name('api.communication.templates.store');
        Route::get('communication/templates', [NotificationTemplateController::class, 'index'])
            ->name('api.communication.templates.index');
        Route::post('communication/messages', [MessageController::class, 'store'])
            ->name('api.communication.messages.store');
        Route::get('communication/messages', [MessageController::class, 'index'])
            ->name('api.communication.messages.index');
        Route::post('communication/messages/{message}/mark-sent', [MessageController::class, 'markSent'])
            ->whereNumber('message')
            ->name('api.communication.messages.mark-sent');

        Route::post('workflow/approval-flows', [ApprovalFlowController::class, 'store'])
            ->name('api.workflow.approval-flows.store');
        Route::get('workflow/approval-flows', [ApprovalFlowController::class, 'index'])
            ->name('api.workflow.approval-flows.index');
        Route::post('workflow/approval-requests', [ApprovalRequestController::class, 'store'])
            ->name('api.workflow.approval-requests.store');
        Route::get('workflow/approval-requests', [ApprovalRequestController::class, 'index'])
            ->name('api.workflow.approval-requests.index');
        Route::post('workflow/approval-requests/{approvalRequest}/decide', [ApprovalRequestController::class, 'decide'])
            ->whereNumber('approvalRequest')
            ->name('api.workflow.approval-requests.decide');
        Route::post('workflow/approval-requests/{approvalRequest}/cancel', [ApprovalRequestController::class, 'cancel'])
            ->whereNumber('approvalRequest')
            ->name('api.workflow.approval-requests.cancel');

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
        Route::post('vocational/workshops', [VocationalController::class, 'storeWorkshop'])
            ->name('api.vocational.workshops.store');
        Route::get('vocational/workshops', [VocationalController::class, 'indexWorkshops'])
            ->name('api.vocational.workshops.index');

        Route::post('hr/job-positions', [HrController::class, 'storePosition'])
            ->name('api.hr.job-positions.store');
        Route::get('hr/job-positions', [HrController::class, 'indexPositions'])
            ->name('api.hr.job-positions.index');
        Route::post('hr/job-positions/{jobPosition}/deactivate', [HrController::class, 'deactivatePosition'])
            ->whereNumber('jobPosition')
            ->name('api.hr.job-positions.deactivate');
        Route::post('hr/job-positions/{jobPosition}/reactivate', [HrController::class, 'reactivatePosition'])
            ->whereNumber('jobPosition')
            ->name('api.hr.job-positions.reactivate');
        Route::post('hr/employees', [HrController::class, 'storeEmployee'])
            ->name('api.hr.employees.store');
        Route::get('hr/employees', [HrController::class, 'indexEmployees'])
            ->name('api.hr.employees.index');
        Route::post('hr/employees/{employee}/deactivate', [HrController::class, 'deactivateEmployee'])
            ->whereNumber('employee')
            ->name('api.hr.employees.deactivate');
        Route::post('hr/employees/{employee}/reactivate', [HrController::class, 'reactivateEmployee'])
            ->whereNumber('employee')
            ->name('api.hr.employees.reactivate');
    });
});
