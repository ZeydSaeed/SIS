<?php

use App\Http\Controllers\Admission\AdmissionPageController;
use App\Http\Controllers\Admission\ApplicationTransferPageController;
use App\Http\Controllers\Attendance\AttendancePageController;
use App\Http\Controllers\Curriculum\CurriculumPageController;
use App\Http\Controllers\Enrollment\ClassSectionPageController;
use App\Http\Controllers\Enrollment\EnrollmentPageController;
use App\Http\Controllers\Exams\ExamPageController;
use App\Http\Controllers\Grades\GradesPageController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Intelligence\RecommendationController;
use App\Http\Controllers\Ops\OpsModulePageController;
use App\Http\Controllers\Organization\BranchStructurePageController;
use App\Http\Controllers\Organization\DirectorateRegistryController;
use App\Http\Controllers\Organization\DirectorateSchoolPageController;
use App\Http\Controllers\Organization\SchoolRegistryController;
use App\Http\Controllers\Reports\ReportsPageController;
use App\Http\Controllers\Results\ResultsPageController;
use App\Http\Controllers\SchoolContextController;
use App\Http\Controllers\Student\StudentPageController;
use App\Http\Controllers\Teachers\TeacherPageController;
use App\Http\Controllers\Timetable\TimetableEngineController;
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
        Route::post('/', [StudentPageController::class, 'store'])->name('store');
        Route::post('/bulk-status', [StudentPageController::class, 'bulkStatus'])->name('bulk-status');
        Route::put('/{student}', [StudentPageController::class, 'update'])->whereNumber('student')->name('update');
        Route::post('/{student}/documents/upload', [StudentPageController::class, 'uploadDocument'])
            ->whereNumber('student')
            ->name('documents.upload');
        Route::get('/{student}', [StudentPageController::class, 'show'])->name('show');
    });

    Route::prefix('organization')->name('organization.')->middleware('require.school.context')->group(function (): void {
        // «المديريات والمدارس» — directorates → schools → branches (writes below; delete = deactivate).
        Route::get('/directorates-schools', [DirectorateSchoolPageController::class, 'index'])->name('directorates-schools.index');
        Route::post('/schools', [SchoolRegistryController::class, 'store'])->name('schools.store');
        Route::patch('/schools/{school}', [SchoolRegistryController::class, 'update'])
            ->whereNumber('school')
            ->name('schools.update');
        Route::post('/schools/{school}/deactivate', [SchoolRegistryController::class, 'deactivate'])
            ->whereNumber('school')
            ->name('schools.deactivate');
        Route::post('/schools/{school}/reactivate', [SchoolRegistryController::class, 'reactivate'])
            ->whereNumber('school')
            ->name('schools.reactivate');
        Route::post('/schools/{school}/archive', [SchoolRegistryController::class, 'archive'])
            ->whereNumber('school')
            ->name('schools.archive');
        Route::post('/directorates', [DirectorateRegistryController::class, 'store'])->name('directorates.store');
        Route::patch('/directorates/{directorate}', [DirectorateRegistryController::class, 'update'])
            ->whereNumber('directorate')
            ->name('directorates.update');
        Route::post('/directorates/{directorate}/deactivate', [DirectorateRegistryController::class, 'deactivate'])
            ->whereNumber('directorate')
            ->name('directorates.deactivate');
        Route::post('/directorates/{directorate}/reactivate', [DirectorateRegistryController::class, 'reactivate'])
            ->whereNumber('directorate')
            ->name('directorates.reactivate');
        Route::post('/directorates/{directorate}/archive', [DirectorateRegistryController::class, 'archive'])
            ->whereNumber('directorate')
            ->name('directorates.archive');

        // «الفروع والاختصاصات» — branches and departments of the current school (delete = deactivate).
        Route::get('/branches', [BranchStructurePageController::class, 'index'])->name('branches.index');
        // «الصفوف والشعب» — classes / sections of the year with capacity and homeroom teacher (delete = deactivate).
        Route::get('/classes-sections', [ClassSectionPageController::class, 'index'])->name('classes-sections.index');
        Route::post('/classes', [ClassSectionPageController::class, 'storeClass'])->name('classes.store');
        Route::patch('/classes/{class}', [ClassSectionPageController::class, 'updateClass'])->whereNumber('class')->name('classes.update');
        Route::post('/classes/{class}/deactivate', [ClassSectionPageController::class, 'deactivateClass'])->whereNumber('class')->name('classes.deactivate');
        Route::post('/classes/{class}/reactivate', [ClassSectionPageController::class, 'reactivateClass'])->whereNumber('class')->name('classes.reactivate');
        Route::post('/sections', [ClassSectionPageController::class, 'storeSection'])->name('sections.store');
        Route::patch('/sections/{section}', [ClassSectionPageController::class, 'updateSection'])->whereNumber('section')->name('sections.update');
        Route::post('/sections/{section}/deactivate', [ClassSectionPageController::class, 'deactivateSection'])->whereNumber('section')->name('sections.deactivate');
        Route::post('/sections/{section}/reactivate', [ClassSectionPageController::class, 'reactivateSection'])->whereNumber('section')->name('sections.reactivate');
        Route::post('/branches', [BranchStructurePageController::class, 'storeBranch'])->name('branches.store');
        Route::patch('/branches/{branch}', [BranchStructurePageController::class, 'updateBranch'])
            ->whereNumber('branch')
            ->name('branches.update');
        Route::post('/branches/{branch}/delete', [BranchStructurePageController::class, 'destroyBranch'])
            ->whereNumber('branch')
            ->name('branches.delete');
        Route::post('/departments', [BranchStructurePageController::class, 'storeDepartment'])->name('departments.store');
        Route::patch('/departments/{department}', [BranchStructurePageController::class, 'updateDepartment'])
            ->whereNumber('department')
            ->name('departments.update');
        Route::post('/departments/delete', [BranchStructurePageController::class, 'destroyDepartments'])
            ->name('departments.delete');
    });

    Route::prefix('admission')->name('admission.')->middleware('require.school.context')->group(function (): void {
        Route::get('/', [AdmissionPageController::class, 'index'])->name('index');
        Route::get('/drafts', [AdmissionPageController::class, 'drafts'])->name('drafts');
        Route::get('/submitted', [AdmissionPageController::class, 'submitted'])->name('submitted');
        Route::get('/under-review', [AdmissionPageController::class, 'underReview'])->name('under-review');
        Route::get('/interview', [AdmissionPageController::class, 'interview'])->name('interview');
        Route::get('/waitlisted', [AdmissionPageController::class, 'waitlisted'])->name('waitlisted');
        Route::get('/accepted', [AdmissionPageController::class, 'accepted'])->name('accepted');
        Route::get('/withdrawn', [AdmissionPageController::class, 'withdrawn'])->name('withdrawn');
        Route::get('/rejected', [AdmissionPageController::class, 'rejected'])->name('rejected');
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
        Route::post('/applications/register-student', [AdmissionPageController::class, 'registerStudent'])
            ->name('applications.register-student');
        Route::put('/applications/follow-up', [AdmissionPageController::class, 'updateFollowUp'])
            ->name('applications.follow-up');
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
        Route::post('/bulk', [EnrollmentPageController::class, 'bulkStore'])->name('bulk-store');
        Route::post('/bulk-status', [EnrollmentPageController::class, 'bulkStatus'])->name('bulk-status');
        Route::post('/bulk-placement', [EnrollmentPageController::class, 'bulkPlacement'])->name('bulk-placement');
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
        Route::post('/', [TeacherPageController::class, 'store'])->name('store');
        Route::patch('/{teacher}', [TeacherPageController::class, 'update'])->whereNumber('teacher')->name('update');
        Route::post('/{teacher}/deactivate', [TeacherPageController::class, 'deactivate'])->whereNumber('teacher')->name('deactivate');
        Route::post('/{teacher}/reactivate', [TeacherPageController::class, 'reactivate'])->whereNumber('teacher')->name('reactivate');
        Route::post('/{teacher}/subjects', [TeacherPageController::class, 'assignSubject'])->whereNumber('teacher')->name('subjects.assign');
        Route::post('/{teacher}/subjects/unlink', [TeacherPageController::class, 'unlinkSubject'])->whereNumber('teacher')->name('subjects.unlink');
        Route::post('/bulk-status', [TeacherPageController::class, 'bulkStatus'])->name('bulk-status');
        Route::post('/bulk-employment-type', [TeacherPageController::class, 'bulkEmploymentType'])->name('bulk-employment-type');
        Route::post('/{teacher}/assignments', [TeacherPageController::class, 'addAssignment'])->whereNumber('teacher')->name('assignments.add');
        Route::post('/{teacher}/assignments/{assignment}/end', [TeacherPageController::class, 'endAssignment'])
            ->whereNumber('teacher')
            ->whereNumber('assignment')
            ->name('assignments.end');
    });

    Route::prefix('timetable')->name('timetable.')->middleware('require.school.context')->group(function (): void {
        Route::get('/', [TimetablePageController::class, 'index'])->name('index');
        Route::get('/{schedule}', [TimetablePageController::class, 'show'])->whereNumber('schedule')->name('show');
        Route::post('/periods', [TimetablePageController::class, 'storePeriod'])->name('periods.store');
        Route::post('/periods/arrange', [TimetablePageController::class, 'arrangeDay'])->name('periods.arrange');
        Route::patch('/periods/{period}', [TimetablePageController::class, 'updatePeriod'])->whereNumber('period')->name('periods.update');
        Route::post('/schedules', [TimetablePageController::class, 'storeSchedule'])->name('schedules.store');
        Route::patch('/schedules/{schedule}', [TimetablePageController::class, 'updateSchedule'])->whereNumber('schedule')->name('schedules.update');
        Route::post('/schedules/{schedule}/cancel', [TimetablePageController::class, 'cancelSchedule'])->whereNumber('schedule')->name('schedules.cancel');
        Route::post('/schedules/{schedule}/swap', [TimetablePageController::class, 'swapSchedules'])->whereNumber('schedule')->name('schedules.swap');
        Route::post('/schedules/{schedule}/shift', [TimetablePageController::class, 'shiftSchedule'])->whereNumber('schedule')->name('schedules.shift');
        Route::post('/schedules/auto-place', [TimetablePageController::class, 'autoPlace'])->name('schedules.auto');
        // Timetable engine (docs/timetable): configuration, generation runs, locks, versions, student view, export.
        Route::post('/schedules/lock', [TimetableEngineController::class, 'lockSchedules'])->name('schedules.lock');
        Route::post('/schedules/{schedule}/substitute', [TimetablePageController::class, 'storeException'])->whereNumber('schedule')->name('schedules.substitute');
        Route::post('/settings', [TimetableEngineController::class, 'saveSettings'])->name('settings.save');
        Route::post('/activities', [TimetableEngineController::class, 'storeActivity'])->name('activities.store');
        Route::post('/activities/sync', [TimetableEngineController::class, 'syncActivities'])->name('activities.sync');
        Route::patch('/activities/{activity}', [TimetableEngineController::class, 'updateActivity'])->whereNumber('activity')->name('activities.update');
        Route::post('/activities/{activity}/end', [TimetableEngineController::class, 'endActivity'])->whereNumber('activity')->name('activities.end');
        Route::post('/divisions', [TimetableEngineController::class, 'storeDivision'])->name('divisions.store');
        Route::post('/divisions/{division}/end', [TimetableEngineController::class, 'endDivision'])->whereNumber('division')->name('divisions.end');
        Route::post('/availability', [TimetableEngineController::class, 'saveAvailability'])->name('availability.save');
        Route::post('/rules', [TimetableEngineController::class, 'storeRule'])->name('rules.store');
        Route::post('/rules/{rule}/end', [TimetableEngineController::class, 'endRule'])->whereNumber('rule')->name('rules.end');
        Route::post('/runs', [TimetableEngineController::class, 'storeRun'])->name('runs.store');
        Route::post('/runs/{run}/cancel', [TimetableEngineController::class, 'cancelRun'])->whereNumber('run')->name('runs.cancel');
        Route::post('/runs/{run}/apply', [TimetableEngineController::class, 'applyRun'])->whereNumber('run')->name('runs.apply');
        Route::post('/runs/{run}/discard', [TimetableEngineController::class, 'discardRun'])->whereNumber('run')->name('runs.discard');
        Route::post('/versions', [TimetableEngineController::class, 'storeVersion'])->name('versions.store');
        Route::post('/versions/{version}/submit', [TimetableEngineController::class, 'submitVersion'])->whereNumber('version')->name('versions.submit');
        Route::post('/versions/{version}/decide', [TimetableEngineController::class, 'decideVersion'])->whereNumber('version')->name('versions.decide');
        Route::post('/versions/{version}/publish', [TimetableEngineController::class, 'publishVersion'])->whereNumber('version')->name('versions.publish');
        Route::post('/versions/{version}/archive', [TimetableEngineController::class, 'archiveVersion'])->whereNumber('version')->name('versions.archive');
        Route::post('/versions/{version}/restore', [TimetableEngineController::class, 'restoreVersion'])->whereNumber('version')->name('versions.restore');
        Route::get('/students/{student}', [TimetablePageController::class, 'student'])->whereNumber('student')->name('students.show');
        Route::get('/export', [TimetablePageController::class, 'export'])->name('export');
    });

    Route::prefix('curriculum')->name('curriculum.')->middleware('require.school.context')->group(function (): void {
        Route::get('/', [CurriculumPageController::class, 'index'])->name('index');
        Route::get('/curricula/{curriculum}', [CurriculumPageController::class, 'show'])
            ->whereNumber('curriculum')
            ->name('show');
        Route::post('/curricula', [CurriculumPageController::class, 'storeCurriculum'])->name('curricula.store');
        Route::patch('/curricula/{curriculum}', [CurriculumPageController::class, 'updateCurriculum'])
            ->whereNumber('curriculum')
            ->name('curricula.update');
        Route::post('/curricula/{curriculum}/deactivate', [CurriculumPageController::class, 'deactivateCurriculum'])
            ->whereNumber('curriculum')
            ->name('curricula.deactivate');
        Route::post('/curricula/{curriculum}/reactivate', [CurriculumPageController::class, 'reactivateCurriculum'])
            ->whereNumber('curriculum')
            ->name('curricula.reactivate');
        Route::post('/curricula/{curriculum}/apply-to-enrollments', [CurriculumPageController::class, 'applyCurriculumToEnrollments'])
            ->whereNumber('curriculum')
            ->name('curricula.apply-to-enrollments');
        Route::post('/curricula/{curriculum}/subjects', [CurriculumPageController::class, 'storeCurriculumSubject'])
            ->whereNumber('curriculum')
            ->name('curriculum_subjects.store');
        Route::post('/curriculum-subjects/{link}/deactivate', [CurriculumPageController::class, 'deactivateCurriculumSubject'])
            ->whereNumber('link')
            ->name('curriculum_subjects.deactivate');
        Route::post('/curriculum-subjects/{link}/reactivate', [CurriculumPageController::class, 'reactivateCurriculumSubject'])
            ->whereNumber('link')
            ->name('curriculum_subjects.reactivate');
        Route::post('/subjects', [CurriculumPageController::class, 'storeSubject'])->name('subjects.store');
        Route::patch('/subjects/{subject}', [CurriculumPageController::class, 'updateSubject'])
            ->whereNumber('subject')
            ->name('subjects.update');
        Route::post('/subjects/{subject}/deactivate', [CurriculumPageController::class, 'deactivateSubject'])
            ->whereNumber('subject')
            ->name('subjects.deactivate');
        Route::post('/subjects/{subject}/reactivate', [CurriculumPageController::class, 'reactivateSubject'])
            ->whereNumber('subject')
            ->name('subjects.reactivate');
        Route::post('/subjects/{subject}/prerequisites', [CurriculumPageController::class, 'storePrerequisite'])
            ->whereNumber('subject')
            ->name('prerequisites.store');
        Route::post('/prerequisites/{prerequisite}/deactivate', [CurriculumPageController::class, 'deactivatePrerequisite'])
            ->whereNumber('prerequisite')
            ->name('prerequisites.deactivate');
        Route::post('/prerequisites/{prerequisite}/reactivate', [CurriculumPageController::class, 'reactivatePrerequisite'])
            ->whereNumber('prerequisite')
            ->name('prerequisites.reactivate');
        Route::post('/enrollments/{enrollment}/subjects', [CurriculumPageController::class, 'assignEnrollmentSubject'])
            ->whereNumber('enrollment')
            ->name('enrollment_subjects.store');
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
        Route::get('/promotion', [OpsModulePageController::class, 'promotion'])->name('promotion.index');
        // النقل — the only place an application's school / request kind / academic year changes.
        Route::get('/transfers', [ApplicationTransferPageController::class, 'index'])->name('transfers.index');
        Route::post('/transfers/applications/{application}', [ApplicationTransferPageController::class, 'store'])
            ->whereNumber('application')
            ->name('transfers.applications.store');
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
