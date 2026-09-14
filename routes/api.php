<?php

use App\Http\Controllers\Api\AcademicController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\CurriculumController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\EnrollmentStructureController;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\GradeController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MetricsController;
use App\Http\Controllers\Api\OrganizationController;
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
use App\Http\Controllers\Api\NotificationJobController;
use App\Http\Controllers\Api\NotificationTemplateController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\StudentFeeController;
use App\Http\Controllers\Api\TransferController;
use App\Http\Controllers\Api\ResultsWriteController;
use App\Http\Controllers\Api\PeriodController;
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
        Route::post('students/{student}/documents', [StudentController::class, 'storeDocument'])
            ->whereNumber('student')
            ->name('api.students.documents.store');
        Route::post('students/{student}/documents/upload', [StudentController::class, 'uploadDocument'])
            ->whereNumber('student')
            ->name('api.students.documents.upload');
        Route::get('students/{student}/documents', [StudentController::class, 'indexDocuments'])
            ->whereNumber('student')
            ->name('api.students.documents.index');
        Route::get('students/{student}/guardians', [StudentController::class, 'indexGuardians'])
            ->whereNumber('student')
            ->name('api.students.guardians.index');
        Route::get('student-documents/{document}', [StudentController::class, 'showDocument'])
            ->whereNumber('document')
            ->name('api.student_documents.show');
        Route::get('student-documents/{document}/content', [StudentController::class, 'downloadDocument'])
            ->whereNumber('document')
            ->name('api.student_documents.download');
        Route::post('student-documents/{document}/void', [StudentController::class, 'voidDocument'])
            ->whereNumber('document')
            ->name('api.student_documents.void');
        Route::post('student-documents/{document}/restore', [StudentController::class, 'restoreDocument'])
            ->whereNumber('document')
            ->name('api.student_documents.restore');

        Route::get('/security/admin-probe', function (Request $request) {
            abort_unless(Gate::allows('security.manage_users'), 403);

            return response()->json(['status' => 'ok']);
        })->name('api.security.admin_probe');

        Route::post('enrollments/{enrollment}/cancel', [EnrollmentController::class, 'cancel'])
            ->name('api.enrollments.cancel');
        Route::post('enrollments/{enrollment}/reopen', [EnrollmentController::class, 'reopen'])
            ->whereNumber('enrollment')
            ->name('api.enrollments.reopen');
        Route::post('enrollments/{enrollment}/subjects', [EnrollmentController::class, 'storeSubject'])
            ->whereNumber('enrollment')
            ->name('api.enrollments.subjects.store');
        Route::get('enrollments/{enrollment}/subjects', [EnrollmentController::class, 'indexSubjects'])
            ->whereNumber('enrollment')
            ->name('api.enrollments.subjects.index');
        Route::post('enrollment-subjects/{link}/deactivate', [EnrollmentController::class, 'deactivateSubject'])
            ->whereNumber('link')
            ->name('api.enrollment_subjects.deactivate');
        Route::post('enrollment-subjects/{link}/reactivate', [EnrollmentController::class, 'reactivateSubject'])
            ->whereNumber('link')
            ->name('api.enrollment_subjects.reactivate');
        Route::get('enrollment-subjects/{link}', [EnrollmentController::class, 'showSubject'])
            ->whereNumber('link')
            ->name('api.enrollment_subjects.show');

        Route::get('academic/years', [AcademicController::class, 'indexYears'])
            ->name('api.academic.years.index');
        Route::post('academic/years', [AcademicController::class, 'storeYear'])
            ->name('api.academic.years.store');
        Route::get('academic/years/{year}', [AcademicController::class, 'showYear'])
            ->whereNumber('year')
            ->name('api.academic.years.show');
        Route::get('academic/grade-levels', [AcademicController::class, 'indexGradeLevels'])
            ->name('api.academic.grade-levels.index');
        Route::get('academic/grade-levels/{gradeLevel}', [AcademicController::class, 'showGradeLevel'])
            ->whereNumber('gradeLevel')
            ->name('api.academic.grade-levels.show');

        Route::get('organization/rooms', [OrganizationController::class, 'indexRooms'])
            ->name('api.organization.rooms.index');

        Route::get('enrollment/classes', [EnrollmentStructureController::class, 'indexClasses'])
            ->name('api.enrollment.classes.index');
        Route::get('enrollment/classes/{class}', [EnrollmentStructureController::class, 'showClass'])
            ->whereNumber('class')
            ->name('api.enrollment.classes.show');
        Route::get('enrollment/classes/{class}/sections', [EnrollmentStructureController::class, 'indexSections'])
            ->whereNumber('class')
            ->name('api.enrollment.sections.index');
        Route::get('enrollment/sections/{section}', [EnrollmentStructureController::class, 'showSection'])
            ->whereNumber('section')
            ->name('api.enrollment.sections.show');
        Route::post('enrollment/classes/{class}/deactivate', [EnrollmentStructureController::class, 'deactivateClass'])
            ->whereNumber('class')
            ->name('api.enrollment.classes.deactivate');
        Route::post('enrollment/classes/{class}/reactivate', [EnrollmentStructureController::class, 'reactivateClass'])
            ->whereNumber('class')
            ->name('api.enrollment.classes.reactivate');
        Route::post('enrollment/sections/{section}/deactivate', [EnrollmentStructureController::class, 'deactivateSection'])
            ->whereNumber('section')
            ->name('api.enrollment.sections.deactivate');
        Route::post('enrollment/sections/{section}/reactivate', [EnrollmentStructureController::class, 'reactivateSection'])
            ->whereNumber('section')
            ->name('api.enrollment.sections.reactivate');

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

        Route::get('exams', [ExamController::class, 'index'])->name('api.exams.index');
        Route::post('exams', [ExamController::class, 'store'])->name('api.exams.store');
        Route::get('exams/{exam}', [ExamController::class, 'show'])->whereNumber('exam')->name('api.exams.show');
        Route::patch('exams/{exam}', [ExamController::class, 'update'])->whereNumber('exam')->name('api.exams.update');
        Route::post('exams/{exam}/cancel', [ExamController::class, 'cancel'])->whereNumber('exam')->name('api.exams.cancel');
        Route::get('exams/{exam}/sessions', [ExamController::class, 'indexSessions'])
            ->whereNumber('exam')
            ->name('api.exams.sessions.index');
        Route::post('exams/{exam}/sessions', [ExamController::class, 'storeSession'])
            ->whereNumber('exam')
            ->name('api.exams.sessions.store');
        Route::get('exam-sessions/{examSession}', [ExamController::class, 'showSession'])
            ->whereNumber('examSession')
            ->name('api.exam_sessions.show');
        Route::patch('exam-sessions/{examSession}', [ExamController::class, 'updateSession'])
            ->whereNumber('examSession')
            ->name('api.exam_sessions.update');
        Route::post('exam-sessions/{examSession}/open', [ExamController::class, 'openSession'])
            ->whereNumber('examSession')
            ->name('api.exam_sessions.open');
        Route::post('exam-sessions/{examSession}/close', [ExamController::class, 'closeSession'])
            ->whereNumber('examSession')
            ->name('api.exam_sessions.close');
        Route::get('exam-sessions/{examSession}/enrollments', [ExamController::class, 'indexEnrollments'])
            ->whereNumber('examSession')
            ->name('api.exam_sessions.enrollments.index');
        Route::post('exam-sessions/{examSession}/enrollments', [ExamController::class, 'storeEnrollment'])
            ->whereNumber('examSession')
            ->name('api.exam_sessions.enrollments.store');
        Route::get('exam-enrollments/{examEnrollment}', [ExamController::class, 'showEnrollment'])
            ->whereNumber('examEnrollment')
            ->name('api.exam_enrollments.show');
        Route::patch('exam-enrollments/{examEnrollment}', [ExamController::class, 'updateEnrollment'])
            ->whereNumber('examEnrollment')
            ->name('api.exam_enrollments.update');
        Route::post('exam-enrollments/{examEnrollment}/cancel', [ExamController::class, 'cancelEnrollment'])
            ->whereNumber('examEnrollment')
            ->name('api.exam_enrollments.cancel');
        Route::post('exam-enrollments/{examEnrollment}/reopen', [ExamController::class, 'reopenEnrollment'])
            ->whereNumber('examEnrollment')
            ->name('api.exam_enrollments.reopen');
        Route::post('exam-enrollments/{examEnrollment}/present', [ExamController::class, 'presentEnrollment'])
            ->whereNumber('examEnrollment')
            ->name('api.exam_enrollments.present');

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
        Route::post('timetable/schedules/{schedule}/reactivate', [ScheduleController::class, 'reactivate'])
            ->whereNumber('schedule')
            ->name('api.timetable.schedules.reactivate');
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
        Route::get('timetable/periods', [PeriodController::class, 'index'])
            ->name('api.timetable.periods.index');
        Route::get('timetable/periods/{period}', [PeriodController::class, 'show'])
            ->whereNumber('period')
            ->name('api.timetable.periods.show');

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
        Route::get('portal/scopes/{scope}', [PortalScopesController::class, 'show'])
            ->whereNumber('scope')
            ->name('api.portal.scopes.show');

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
        Route::post('teachers/{teacher}/reactivate', [TeacherController::class, 'reactivate'])
            ->name('api.teachers.reactivate');
        Route::post('teachers/{teacher}/assign-school', [TeacherController::class, 'assignSchool'])
            ->whereNumber('teacher')
            ->name('api.teachers.assign-school');
        Route::post('teachers/{teacher}/change-employee-code', [TeacherController::class, 'changeEmployeeCode'])
            ->whereNumber('teacher')
            ->name('api.teachers.change-employee-code');
        Route::post('teachers/{teacher}/set-primary-school', [TeacherController::class, 'setPrimarySchool'])
            ->whereNumber('teacher')
            ->name('api.teachers.set-primary-school');
        Route::post('teachers/{teacher}/leave-school', [TeacherController::class, 'leaveSchool'])
            ->whereNumber('teacher')
            ->name('api.teachers.leave-school');
        Route::get('teachers/{teacher}/subjects', [TeacherController::class, 'indexSubjects'])
            ->whereNumber('teacher')
            ->name('api.teachers.subjects.index');
        Route::get('teachers/{teacher}/subjects/{assignment}', [TeacherController::class, 'showSubject'])
            ->whereNumber('teacher')
            ->whereNumber('assignment')
            ->name('api.teachers.subjects.show');
        Route::get('teachers/{teacher}/schools', [TeacherController::class, 'indexSchools'])
            ->whereNumber('teacher')
            ->name('api.teachers.schools.index');
        Route::get('teachers/{teacher}/schools/{membership}', [TeacherController::class, 'showSchool'])
            ->whereNumber('teacher')
            ->whereNumber('membership')
            ->name('api.teachers.schools.show');
        Route::post('teachers/{teacher}/subjects', [TeacherController::class, 'assignSubject'])
            ->name('api.teachers.subjects.assign');
        Route::delete('teachers/{teacher}/subjects', [TeacherController::class, 'unlinkSubject'])
            ->name('api.teachers.subjects.unlink');
        Route::post('teachers/{teacher}/qualifications', [TeacherController::class, 'storeQualification'])
            ->name('api.teachers.qualifications.store');
        Route::get('teachers/{teacher}/qualifications', [TeacherController::class, 'indexQualifications'])
            ->name('api.teachers.qualifications.index');
        Route::get('teachers/{teacher}/qualifications/{qualification}', [TeacherController::class, 'showQualification'])
            ->whereNumber('teacher')
            ->whereNumber('qualification')
            ->name('api.teachers.qualifications.show');
        Route::post('teachers/{teacher}/qualifications/{qualification}/void', [TeacherController::class, 'voidQualification'])
            ->whereNumber('teacher')
            ->whereNumber('qualification')
            ->name('api.teachers.qualifications.void');
        Route::post('teachers/{teacher}/qualifications/{qualification}/restore', [TeacherController::class, 'restoreQualification'])
            ->whereNumber('teacher')
            ->whereNumber('qualification')
            ->name('api.teachers.qualifications.restore');
        Route::post('teachers/{teacher}/qualifications/{qualification}/attach-document', [TeacherController::class, 'attachQualificationDocument'])
            ->whereNumber('teacher')
            ->whereNumber('qualification')
            ->name('api.teachers.qualifications.attach-document');

        Route::post('promotion/rules', [PromotionController::class, 'storeRule'])
            ->name('api.promotion.rules.store');
        Route::get('promotion/rules', [PromotionController::class, 'indexRules'])
            ->name('api.promotion.rules.index');
        Route::get('promotion/rules/{rule}', [PromotionController::class, 'showRule'])
            ->whereNumber('rule')
            ->name('api.promotion.rules.show');
        Route::post('promotion/rules/{rule}/deactivate', [PromotionController::class, 'deactivateRule'])
            ->whereNumber('rule')
            ->name('api.promotion.rules.deactivate');
        Route::post('promotion/rules/{rule}/reactivate', [PromotionController::class, 'reactivateRule'])
            ->whereNumber('rule')
            ->name('api.promotion.rules.reactivate');
        Route::post('promotion/records', [PromotionController::class, 'storeRecord'])
            ->name('api.promotion.records.store');
        Route::get('promotion/records', [PromotionController::class, 'indexRecords'])
            ->name('api.promotion.records.index');
        Route::get('promotion/records/{record}', [PromotionController::class, 'showRecord'])
            ->whereNumber('record')
            ->name('api.promotion.records.show');

        Route::post('transfers/requests', [TransferController::class, 'store'])
            ->name('api.transfers.requests.store');
        Route::get('transfers/requests', [TransferController::class, 'index'])
            ->name('api.transfers.requests.index');
        Route::get('transfers/requests/{transferRequest}', [TransferController::class, 'show'])
            ->whereNumber('transferRequest')
            ->name('api.transfers.requests.show');
        Route::post('transfers/requests/{transferRequest}/approve', [TransferController::class, 'approve'])
            ->name('api.transfers.requests.approve');
        Route::post('transfers/requests/{transferRequest}/reject', [TransferController::class, 'reject'])
            ->name('api.transfers.requests.reject');
        Route::post('transfers/requests/{transferRequest}/complete', [TransferController::class, 'complete'])
            ->name('api.transfers.requests.complete');
        Route::post('transfers/requests/{transferRequest}/cancel', [TransferController::class, 'cancel'])
            ->name('api.transfers.requests.cancel');
        Route::post('transfers/requests/{transferRequest}/reopen', [TransferController::class, 'reopen'])
            ->whereNumber('transferRequest')
            ->name('api.transfers.requests.reopen');
        Route::get('transfers/records', [TransferController::class, 'indexRecords'])
            ->name('api.transfers.records.index');
        Route::get('transfers/records/{record}', [TransferController::class, 'showRecord'])
            ->whereNumber('record')
            ->name('api.transfers.records.show');

        Route::post('documents', [DocumentController::class, 'store'])
            ->name('api.documents.store');
        Route::post('documents/upload', [DocumentController::class, 'upload'])
            ->name('api.documents.upload');
        Route::get('documents/{document}/content', [DocumentController::class, 'download'])
            ->whereNumber('document')
            ->name('api.documents.download');
        Route::get('documents', [DocumentController::class, 'index'])
            ->name('api.documents.index');
        Route::get('documents/{document}', [DocumentController::class, 'show'])
            ->whereNumber('document')
            ->name('api.documents.show');

        Route::post('audit/logs', [AuditLogController::class, 'store'])
            ->name('api.audit.logs.store');
        Route::get('audit/logs', [AuditLogController::class, 'index'])
            ->name('api.audit.logs.index');
        Route::get('audit/logs/{log}', [AuditLogController::class, 'show'])
            ->whereNumber('log')
            ->name('api.audit.logs.show');
        Route::post('audit/login-history', [AuditLogController::class, 'storeLoginHistory'])
            ->name('api.audit.login_history.store');
        Route::get('audit/login-history', [AuditLogController::class, 'indexLoginHistory'])
            ->name('api.audit.login_history.index');
        Route::get('audit/login-history/{entry}', [AuditLogController::class, 'showLoginHistory'])
            ->whereNumber('entry')
            ->name('api.audit.login_history.show');

        Route::post('finance/fee-types', [FeeTypeController::class, 'store'])
            ->name('api.finance.fee-types.store');
        Route::get('finance/fee-types', [FeeTypeController::class, 'index'])
            ->name('api.finance.fee-types.index');
        Route::get('finance/fee-types/{feeType}', [FeeTypeController::class, 'show'])
            ->whereNumber('feeType')
            ->name('api.finance.fee-types.show');
        Route::post('finance/fee-types/{feeType}/deactivate', [FeeTypeController::class, 'deactivate'])
            ->whereNumber('feeType')
            ->name('api.finance.fee-types.deactivate');
        Route::post('finance/fee-types/{feeType}/reactivate', [FeeTypeController::class, 'reactivate'])
            ->whereNumber('feeType')
            ->name('api.finance.fee-types.reactivate');
        Route::post('finance/student-fees', [StudentFeeController::class, 'store'])
            ->name('api.finance.student-fees.store');
        Route::get('finance/student-fees', [StudentFeeController::class, 'index'])
            ->name('api.finance.student-fees.index');
        Route::get('finance/student-fees/{studentFee}', [StudentFeeController::class, 'show'])
            ->whereNumber('studentFee')
            ->name('api.finance.student-fees.show');
        Route::post('finance/student-fees/{studentFee}/cancel', [StudentFeeController::class, 'cancel'])
            ->whereNumber('studentFee')
            ->name('api.finance.student-fees.cancel');
        Route::post('finance/student-fees/{studentFee}/reopen', [StudentFeeController::class, 'reopen'])
            ->whereNumber('studentFee')
            ->name('api.finance.student-fees.reopen');
        Route::post('finance/payments', [PaymentController::class, 'store'])
            ->name('api.finance.payments.store');
        Route::get('finance/payments', [PaymentController::class, 'index'])
            ->name('api.finance.payments.index');
        Route::get('finance/payments/{payment}', [PaymentController::class, 'show'])
            ->whereNumber('payment')
            ->name('api.finance.payments.show');
        Route::post('finance/payments/{payment}/void', [PaymentController::class, 'void'])
            ->whereNumber('payment')
            ->name('api.finance.payments.void');
        Route::post('finance/payments/{payment}/restore', [PaymentController::class, 'restore'])
            ->whereNumber('payment')
            ->name('api.finance.payments.restore');
        Route::get('finance/transactions', [FinanceTransactionController::class, 'index'])
            ->name('api.finance.transactions.index');
        Route::get('finance/transactions/{transaction}', [FinanceTransactionController::class, 'show'])
            ->whereNumber('transaction')
            ->name('api.finance.transactions.show');

        Route::post('communication/templates', [NotificationTemplateController::class, 'store'])
            ->name('api.communication.templates.store');
        Route::get('communication/templates', [NotificationTemplateController::class, 'index'])
            ->name('api.communication.templates.index');
        Route::get('communication/templates/{template}', [NotificationTemplateController::class, 'show'])
            ->whereNumber('template')
            ->name('api.communication.templates.show');
        Route::post('communication/templates/{template}/deactivate', [NotificationTemplateController::class, 'deactivate'])
            ->whereNumber('template')
            ->name('api.communication.templates.deactivate');
        Route::post('communication/templates/{template}/reactivate', [NotificationTemplateController::class, 'reactivate'])
            ->whereNumber('template')
            ->name('api.communication.templates.reactivate');
        Route::post('communication/messages', [MessageController::class, 'store'])
            ->name('api.communication.messages.store');
        Route::get('communication/messages', [MessageController::class, 'index'])
            ->name('api.communication.messages.index');
        Route::get('communication/messages/{message}', [MessageController::class, 'show'])
            ->whereNumber('message')
            ->name('api.communication.messages.show');
        Route::post('communication/messages/{message}/mark-sent', [MessageController::class, 'markSent'])
            ->whereNumber('message')
            ->name('api.communication.messages.mark-sent');
        Route::post('communication/messages/{message}/cancel', [MessageController::class, 'cancel'])
            ->whereNumber('message')
            ->name('api.communication.messages.cancel');
        Route::post('communication/messages/{message}/requeue', [MessageController::class, 'requeue'])
            ->whereNumber('message')
            ->name('api.communication.messages.requeue');
        Route::post('communication/jobs', [NotificationJobController::class, 'store'])
            ->name('api.communication.jobs.store');
        Route::get('communication/jobs', [NotificationJobController::class, 'index'])
            ->name('api.communication.jobs.index');
        Route::get('communication/jobs/{job}', [NotificationJobController::class, 'show'])
            ->whereNumber('job')
            ->name('api.communication.jobs.show');
        Route::post('communication/jobs/{job}/complete', [NotificationJobController::class, 'complete'])
            ->whereNumber('job')
            ->name('api.communication.jobs.complete');
        Route::post('communication/jobs/{job}/cancel', [NotificationJobController::class, 'cancel'])
            ->whereNumber('job')
            ->name('api.communication.jobs.cancel');
        Route::post('communication/jobs/{job}/reopen', [NotificationJobController::class, 'reopen'])
            ->whereNumber('job')
            ->name('api.communication.jobs.reopen');

        Route::post('workflow/approval-flows', [ApprovalFlowController::class, 'store'])
            ->name('api.workflow.approval-flows.store');
        Route::get('workflow/approval-flows', [ApprovalFlowController::class, 'index'])
            ->name('api.workflow.approval-flows.index');
        Route::get('workflow/approval-flows/{approvalFlow}', [ApprovalFlowController::class, 'show'])
            ->whereNumber('approvalFlow')
            ->name('api.workflow.approval-flows.show');
        Route::post('workflow/approval-flows/{approvalFlow}/deactivate', [ApprovalFlowController::class, 'deactivate'])
            ->whereNumber('approvalFlow')
            ->name('api.workflow.approval-flows.deactivate');
        Route::post('workflow/approval-flows/{approvalFlow}/reactivate', [ApprovalFlowController::class, 'reactivate'])
            ->whereNumber('approvalFlow')
            ->name('api.workflow.approval-flows.reactivate');
        Route::post('workflow/approval-requests', [ApprovalRequestController::class, 'store'])
            ->name('api.workflow.approval-requests.store');
        Route::get('workflow/approval-requests', [ApprovalRequestController::class, 'index'])
            ->name('api.workflow.approval-requests.index');
        Route::get('workflow/approval-requests/{approvalRequest}', [ApprovalRequestController::class, 'show'])
            ->whereNumber('approvalRequest')
            ->name('api.workflow.approval-requests.show');
        Route::post('workflow/approval-requests/{approvalRequest}/decide', [ApprovalRequestController::class, 'decide'])
            ->whereNumber('approvalRequest')
            ->name('api.workflow.approval-requests.decide');
        Route::post('workflow/approval-requests/{approvalRequest}/cancel', [ApprovalRequestController::class, 'cancel'])
            ->whereNumber('approvalRequest')
            ->name('api.workflow.approval-requests.cancel');
        Route::post('workflow/approval-requests/{approvalRequest}/reopen', [ApprovalRequestController::class, 'reopen'])
            ->whereNumber('approvalRequest')
            ->name('api.workflow.approval-requests.reopen');

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
        Route::post('vocational/specializations/{specialization}/reactivate', [VocationalController::class, 'reactivateSpecialization'])
            ->whereNumber('specialization')
            ->name('api.vocational.specializations.reactivate');
        Route::get('vocational/specializations/{specialization}/tracks', [VocationalController::class, 'indexTracks'])
            ->whereNumber('specialization')
            ->name('api.vocational.tracks.index');
        Route::post('vocational/specializations/{specialization}/tracks', [VocationalController::class, 'storeTrack'])
            ->name('api.vocational.tracks.store');
        Route::get('vocational/tracks/{track}', [VocationalController::class, 'showTrack'])
            ->whereNumber('track')
            ->name('api.vocational.tracks.show');
        Route::patch('vocational/tracks/{track}', [VocationalController::class, 'updateTrack'])
            ->name('api.vocational.tracks.update');
        Route::post('vocational/tracks/{track}/deactivate', [VocationalController::class, 'deactivateTrack'])
            ->name('api.vocational.tracks.deactivate');
        Route::post('vocational/tracks/{track}/reactivate', [VocationalController::class, 'reactivateTrack'])
            ->whereNumber('track')
            ->name('api.vocational.tracks.reactivate');
        Route::post('vocational/specializations/{specialization}/subjects', [VocationalController::class, 'linkSubject'])
            ->name('api.vocational.specialization_subjects.store');
        Route::get('vocational/specializations/{specialization}/subjects', [VocationalController::class, 'indexSubjectLinks'])
            ->whereNumber('specialization')
            ->name('api.vocational.specialization_subjects.index');
        Route::get('vocational/specialization-subjects/{link}', [VocationalController::class, 'showSubjectLink'])
            ->whereNumber('link')
            ->name('api.vocational.specialization_subjects.show');
        Route::post('vocational/specialization-subjects/{link}/deactivate', [VocationalController::class, 'deactivateSubjectLink'])
            ->name('api.vocational.specialization_subjects.deactivate');
        Route::post('vocational/specialization-subjects/{link}/reactivate', [VocationalController::class, 'reactivateSubjectLink'])
            ->whereNumber('link')
            ->name('api.vocational.specialization_subjects.reactivate');
        Route::post('vocational/workshops', [VocationalController::class, 'storeWorkshop'])
            ->name('api.vocational.workshops.store');
        Route::get('vocational/workshops', [VocationalController::class, 'indexWorkshops'])
            ->name('api.vocational.workshops.index');
        Route::get('vocational/workshops/{workshop}', [VocationalController::class, 'showWorkshop'])
            ->whereNumber('workshop')
            ->name('api.vocational.workshops.show');
        Route::post('vocational/workshops/{workshop}/deactivate', [VocationalController::class, 'deactivateWorkshop'])
            ->whereNumber('workshop')
            ->name('api.vocational.workshops.deactivate');
        Route::post('vocational/workshops/{workshop}/reactivate', [VocationalController::class, 'reactivateWorkshop'])
            ->whereNumber('workshop')
            ->name('api.vocational.workshops.reactivate');
        Route::post('vocational/workshops/{workshop}/equipment', [VocationalController::class, 'storeWorkshopEquipment'])
            ->whereNumber('workshop')
            ->name('api.vocational.workshop_equipment.store');
        Route::get('vocational/workshops/{workshop}/equipment', [VocationalController::class, 'indexWorkshopEquipment'])
            ->whereNumber('workshop')
            ->name('api.vocational.workshop_equipment.index');
        Route::get('vocational/workshop-equipment/{equipment}', [VocationalController::class, 'showWorkshopEquipment'])
            ->whereNumber('equipment')
            ->name('api.vocational.workshop_equipment.show');
        Route::post('vocational/workshop-equipment/{equipment}/deactivate', [VocationalController::class, 'deactivateWorkshopEquipment'])
            ->whereNumber('equipment')
            ->name('api.vocational.workshop_equipment.deactivate');
        Route::post('vocational/workshop-equipment/{equipment}/reactivate', [VocationalController::class, 'reactivateWorkshopEquipment'])
            ->whereNumber('equipment')
            ->name('api.vocational.workshop_equipment.reactivate');

        Route::post('hr/job-positions', [HrController::class, 'storePosition'])
            ->name('api.hr.job-positions.store');
        Route::get('hr/job-positions', [HrController::class, 'indexPositions'])
            ->name('api.hr.job-positions.index');
        Route::get('hr/job-positions/{jobPosition}', [HrController::class, 'showPosition'])
            ->whereNumber('jobPosition')
            ->name('api.hr.job-positions.show');
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
        Route::get('hr/employees/{employee}', [HrController::class, 'showEmployee'])
            ->whereNumber('employee')
            ->name('api.hr.employees.show');
        Route::post('hr/employees/{employee}/deactivate', [HrController::class, 'deactivateEmployee'])
            ->whereNumber('employee')
            ->name('api.hr.employees.deactivate');
        Route::post('hr/employees/{employee}/reactivate', [HrController::class, 'reactivateEmployee'])
            ->whereNumber('employee')
            ->name('api.hr.employees.reactivate');

        Route::post('curriculum/subjects', [CurriculumController::class, 'storeSubject'])
            ->name('api.curriculum.subjects.store');
        Route::get('curriculum/subjects', [CurriculumController::class, 'indexSubjects'])
            ->name('api.curriculum.subjects.index');
        Route::get('curriculum/subjects/{subject}', [CurriculumController::class, 'showSubject'])
            ->whereNumber('subject')
            ->name('api.curriculum.subjects.show');
        Route::post('curriculum/subjects/{subject}/deactivate', [CurriculumController::class, 'deactivateSubject'])
            ->whereNumber('subject')
            ->name('api.curriculum.subjects.deactivate');
        Route::post('curriculum/subjects/{subject}/reactivate', [CurriculumController::class, 'reactivateSubject'])
            ->whereNumber('subject')
            ->name('api.curriculum.subjects.reactivate');
        Route::patch('curriculum/subjects/{subject}', [CurriculumController::class, 'updateSubject'])
            ->whereNumber('subject')
            ->name('api.curriculum.subjects.update');
        Route::post('curriculum/curricula', [CurriculumController::class, 'storeCurriculum'])
            ->name('api.curriculum.curricula.store');
        Route::get('curriculum/curricula', [CurriculumController::class, 'indexCurricula'])
            ->name('api.curriculum.curricula.index');
        Route::get('curriculum/curricula/{curriculum}', [CurriculumController::class, 'showCurriculum'])
            ->whereNumber('curriculum')
            ->name('api.curriculum.curricula.show');
        Route::post('curriculum/curricula/{curriculum}/deactivate', [CurriculumController::class, 'deactivateCurriculum'])
            ->whereNumber('curriculum')
            ->name('api.curriculum.curricula.deactivate');
        Route::post('curriculum/curricula/{curriculum}/reactivate', [CurriculumController::class, 'reactivateCurriculum'])
            ->whereNumber('curriculum')
            ->name('api.curriculum.curricula.reactivate');
        Route::patch('curriculum/curricula/{curriculum}', [CurriculumController::class, 'updateCurriculum'])
            ->whereNumber('curriculum')
            ->name('api.curriculum.curricula.update');
        Route::post('curriculum/curricula/{curriculum}/subjects', [CurriculumController::class, 'storeCurriculumSubject'])
            ->whereNumber('curriculum')
            ->name('api.curriculum.curriculum_subjects.store');
        Route::get('curriculum/curricula/{curriculum}/subjects', [CurriculumController::class, 'indexCurriculumSubjects'])
            ->whereNumber('curriculum')
            ->name('api.curriculum.curriculum_subjects.index');
        Route::get('curriculum/curriculum-subjects/{link}', [CurriculumController::class, 'showCurriculumSubject'])
            ->whereNumber('link')
            ->name('api.curriculum.curriculum_subjects.show');
        Route::post('curriculum/curriculum-subjects/{link}/deactivate', [CurriculumController::class, 'deactivateCurriculumSubject'])
            ->whereNumber('link')
            ->name('api.curriculum.curriculum_subjects.deactivate');
        Route::post('curriculum/curriculum-subjects/{link}/reactivate', [CurriculumController::class, 'reactivateCurriculumSubject'])
            ->whereNumber('link')
            ->name('api.curriculum.curriculum_subjects.reactivate');
        Route::post('curriculum/subjects/{subject}/prerequisites', [CurriculumController::class, 'storePrerequisite'])
            ->whereNumber('subject')
            ->name('api.curriculum.prerequisites.store');
        Route::get('curriculum/subjects/{subject}/prerequisites', [CurriculumController::class, 'indexPrerequisites'])
            ->whereNumber('subject')
            ->name('api.curriculum.prerequisites.index');
        Route::post('curriculum/prerequisites/{prerequisite}/deactivate', [CurriculumController::class, 'deactivatePrerequisite'])
            ->whereNumber('prerequisite')
            ->name('api.curriculum.prerequisites.deactivate');
        Route::post('curriculum/prerequisites/{prerequisite}/reactivate', [CurriculumController::class, 'reactivatePrerequisite'])
            ->whereNumber('prerequisite')
            ->name('api.curriculum.prerequisites.reactivate');
        Route::get('curriculum/prerequisites/{prerequisite}', [CurriculumController::class, 'showPrerequisite'])
            ->whereNumber('prerequisite')
            ->name('api.curriculum.prerequisites.show');
    });
});
