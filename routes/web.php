<?php

use App\Http\Controllers\Academic\HolidayPageController;
use App\Http\Controllers\Admission\AdmissionPageController;
use App\Http\Controllers\Admission\ApplicationTransferPageController;
use App\Http\Controllers\Attendance\AttendanceAlertController;
use App\Http\Controllers\Attendance\AttendanceExcuseController;
use App\Http\Controllers\Attendance\AttendancePageController;
use App\Http\Controllers\Attendance\AttendancePolicyController;
use App\Http\Controllers\Attendance\AttendanceReportController;
use App\Http\Controllers\Attendance\AttendanceReviewController;
use App\Http\Controllers\Attendance\AttendanceSheetController;
use App\Http\Controllers\Attendance\StaffAttendanceController;
use App\Http\Controllers\Curriculum\CurriculumPageController;
use App\Http\Controllers\Enrollment\ClassSectionPageController;
use App\Http\Controllers\Enrollment\EnrollmentPageController;
use App\Http\Controllers\Exams\ExamAdministrationController;
use App\Http\Controllers\Exams\ExamPageController;
use App\Http\Controllers\Exams\ExamRosterController;
use App\Http\Controllers\Exams\ExamSeatingController;
use App\Http\Controllers\Exams\ExamTypeController;
use App\Http\Controllers\Grades\GradeSheetController;
use App\Http\Controllers\Grades\GradeReviewController;
use App\Http\Controllers\Grades\GradesPageController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Imports\ImportPageController;
use App\Http\Controllers\Intelligence\RecommendationController;
use App\Http\Controllers\Leave\LeaveDelegationController;
use App\Http\Controllers\Leave\LeaveNotificationController;
use App\Http\Controllers\Leave\LeavePageController;
use App\Http\Controllers\Leave\LeaveRequestController;
use App\Http\Controllers\Leave\LeaveSettingsController;
use App\Http\Controllers\Ops\OpsModulePageController;
use App\Http\Controllers\Organization\BranchStructurePageController;
use App\Http\Controllers\Organization\DirectorateRegistryController;
use App\Http\Controllers\Organization\DirectorateSchoolPageController;
use App\Http\Controllers\Organization\RoomCataloguePageController;
use App\Http\Controllers\Organization\SchoolRegistryController;
use App\Http\Controllers\Reports\ReportsPageController;
use App\Http\Controllers\Results\ResultsPageController;
use App\Http\Controllers\Portal\PortalResultsPageController;
use App\Http\Controllers\Results\TermResultSheetController;
use App\Http\Controllers\Results\YearResultSheetController;
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
        // The module's overview (cards) opens first; the list is the module's own path.
        Route::get('/overview', [StudentPageController::class, 'index'])->name('overview');
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
        Route::patch('/classes-sections/appearance', [ClassSectionPageController::class, 'updateAppearance'])->name('classes-sections.appearance');
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

        // «الغرف الدراسية» — rooms and managed room types (source of truth for the timetable; delete = out of service).
        Route::get('/rooms', [RoomCataloguePageController::class, 'index'])->name('rooms.index');
        Route::get('/rooms/overview', [RoomCataloguePageController::class, 'index'])->name('rooms.overview');
        Route::post('/rooms', [RoomCataloguePageController::class, 'storeRoom'])->name('rooms.store');
        Route::patch('/rooms/{room}', [RoomCataloguePageController::class, 'updateRoom'])->whereNumber('room')->name('rooms.update');
        Route::post('/rooms/{room}/status', [RoomCataloguePageController::class, 'changeRoomStatus'])->whereNumber('room')->name('rooms.status');
        Route::post('/room-types', [RoomCataloguePageController::class, 'storeType'])->name('room-types.store');
        Route::patch('/room-types/{type}', [RoomCataloguePageController::class, 'updateType'])->whereNumber('type')->name('room-types.update');
        Route::post('/room-types/{type}/status', [RoomCataloguePageController::class, 'changeTypeStatus'])->whereNumber('type')->name('room-types.status');
        // «الاختصار واللون» of a branch / department / room / room type (the timetable's context menu uses it too).
        Route::patch('/appearance', [RoomCataloguePageController::class, 'updateAppearance'])->name('appearance.update');
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
        // «متابعة طلبات التقديم»: a full page with its own ribbon (was a window over the admission page).
        Route::get('/follow-up', [AdmissionPageController::class, 'followUp'])->name('follow-up');
        // Its derived list: the applications of one category (or all), filtered and paged.
        Route::get('/follow-up/list', [AdmissionPageController::class, 'followUpList'])->name('follow-up.list');
        // One application of the follow-up roster: data, documents, history and its status actions.
        Route::get('/follow-up/applications/{application}', [AdmissionPageController::class, 'followUpShow'])
            ->whereNumber('application')
            ->name('follow-up.show');
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
        Route::get('/overview', [EnrollmentPageController::class, 'index'])->name('overview');
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
        // Attendance & absence management (teacher day → sheet → review), staff, excuses, reports, alerts, policy.
        Route::get('/my', [AttendanceSheetController::class, 'myDay'])->name('my');
        Route::post('/lessons/open', [AttendanceSheetController::class, 'openLesson'])->name('lessons.open');
        Route::post('/lessons/exceptional', [AttendanceSheetController::class, 'openExceptional'])->name('lessons.exceptional');
        Route::get('/sheets/{session}', [AttendanceSheetController::class, 'show'])->whereNumber('session')->name('sheets.show');
        Route::post('/sheets/{session}/draft', [AttendanceSheetController::class, 'saveDraft'])->whereNumber('session')->name('sheets.draft');
        Route::post('/sheets/{session}/submit', [AttendanceSheetController::class, 'submit'])->whereNumber('session')->name('sheets.submit');
        Route::post('/sheets/{session}/review', [AttendanceSheetController::class, 'review'])->whereNumber('session')->name('sheets.review');
        Route::post('/sheets/{session}/students/{student}/correct', [AttendanceSheetController::class, 'correct'])
            ->whereNumber(['session', 'student'])->name('sheets.correct');
        Route::get('/review', [AttendanceReviewController::class, 'index'])->name('review');
        Route::post('/review/bulk-approve', [AttendanceReviewController::class, 'bulkApprove'])->name('review.bulk-approve');
        Route::get('/dashboard', [AttendanceReviewController::class, 'dashboard'])->name('dashboard');
        Route::get('/excuses', [AttendanceExcuseController::class, 'index'])->name('excuses.index');
        Route::post('/excuses', [AttendanceExcuseController::class, 'store'])->name('excuses.store');
        Route::post('/excuses/{excuse}/decide', [AttendanceExcuseController::class, 'decide'])->whereNumber('excuse')->name('excuses.decide');
        Route::get('/staff', [StaffAttendanceController::class, 'index'])->name('staff.index');
        Route::post('/staff', [StaffAttendanceController::class, 'store'])->name('staff.store');
        Route::post('/staff/check-in', [StaffAttendanceController::class, 'checkIn'])->middleware('throttle:10,1')->name('staff.check-in');
        Route::post('/staff/codes', [StaffAttendanceController::class, 'issueCode'])->middleware('throttle:20,1')->name('staff.codes');
        Route::post('/staff/nfc-token', [StaffAttendanceController::class, 'issueNfcToken'])->middleware('throttle:10,1')->name('staff.nfc-token');
        Route::post('/staff/teachers/{teacher}/account', [StaffAttendanceController::class, 'linkAccount'])->whereNumber('teacher')->name('staff.teacher-account');
        Route::get('/reports', [AttendanceReportController::class, 'index'])->name('reports');
        Route::get('/reports/export', [AttendanceReportController::class, 'export'])->middleware('throttle:20,1')->name('reports.export');
        Route::post('/reports/exports', [AttendanceReportController::class, 'requestExport'])->middleware('throttle:10,1')->name('reports.exports.store');
        Route::get('/reports/exports/{export}/download', [AttendanceReportController::class, 'download'])->whereNumber('export')->name('reports.exports.download');
        Route::get('/alerts', [AttendanceAlertController::class, 'index'])->name('alerts.index');
        Route::post('/alerts/detect', [AttendanceAlertController::class, 'detect'])->middleware('throttle:6,1')->name('alerts.detect');
        Route::post('/alerts/{alert}', [AttendanceAlertController::class, 'update'])->whereNumber('alert')->name('alerts.update');
        Route::get('/settings', [AttendancePolicyController::class, 'edit'])->name('settings');
        Route::post('/settings', [AttendancePolicyController::class, 'update'])->name('settings.update');

        // The module's overview: a card per section of the side rail.
        Route::inertia('/overview', 'attendance/overview')->name('overview');
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
        Route::get('/overview', [TeacherPageController::class, 'index'])->name('overview');
        Route::get('/{teacher}', [TeacherPageController::class, 'show'])->whereNumber('teacher')->name('show');
        Route::post('/', [TeacherPageController::class, 'store'])->name('store');
        Route::patch('/{teacher}', [TeacherPageController::class, 'update'])->whereNumber('teacher')->name('update');
        Route::patch('/{teacher}/appearance', [TeacherPageController::class, 'updateAppearance'])->whereNumber('teacher')->name('appearance');
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
        Route::post('/periods/reshape', [TimetablePageController::class, 'reshapeDay'])->name('periods.reshape');
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
        Route::post('/places/rooms', [TimetableEngineController::class, 'storeRoom'])->name('places.rooms.store');
        Route::patch('/places/rooms/{room}', [TimetableEngineController::class, 'updateRoom'])->whereNumber('room')->name('places.rooms.update');
        Route::post('/places/workshops', [TimetableEngineController::class, 'storeWorkshop'])->name('places.workshops.store');
        Route::patch('/places/workshops/{workshop}', [TimetableEngineController::class, 'updateWorkshop'])->whereNumber('workshop')->name('places.workshops.update');
        Route::post('/places/status', [TimetableEngineController::class, 'changePlaceStatus'])->name('places.status');
        Route::post('/rules', [TimetableEngineController::class, 'storeRule'])->name('rules.store');
        Route::post('/rules/{rule}/end', [TimetableEngineController::class, 'endRule'])->whereNumber('rule')->name('rules.end');
        Route::post('/runs', [TimetableEngineController::class, 'storeRun'])->name('runs.store');
        Route::post('/test/marks', [TimetableEngineController::class, 'markTestIssue'])->name('test.marks');
        Route::post('/display', [TimetableEngineController::class, 'saveDisplay'])->name('display.save');
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

    // «مركز الاستيراد» — Excel / CSV import (template → upload → preview → commit → report).
    Route::prefix('imports')->name('imports.')->middleware('require.school.context')->group(function (): void {
        Route::get('/', [ImportPageController::class, 'index'])->name('index');
        Route::get('/templates/{kind}', [ImportPageController::class, 'template'])->whereIn('kind', ['teachers', 'structure', 'students', 'subjects'])->name('template');
        Route::post('/batches/{batch}/commit', [ImportPageController::class, 'commit'])->whereNumber('batch')->name('commit');
        Route::post('/batches/{batch}/cancel', [ImportPageController::class, 'cancel'])->whereNumber('batch')->name('cancel');
        Route::get('/batches/{batch}/errors', [ImportPageController::class, 'errors'])->whereNumber('batch')->name('errors');
        Route::post('/{kind}', [ImportPageController::class, 'store'])->whereIn('kind', ['teachers', 'structure', 'students', 'subjects'])->name('store');
    });

    Route::prefix('curriculum')->name('curriculum.')->middleware('require.school.context')->group(function (): void {
        Route::get('/', [CurriculumPageController::class, 'index'])->name('index');
        Route::get('/overview', [CurriculumPageController::class, 'index'])->name('overview');
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

    Route::get('portal/results', [PortalResultsPageController::class, 'show'])->middleware('require.school.context')->name('portal.results');

    Route::prefix('results')->name('results.')->middleware('require.school.context')->group(function (): void {
        Route::get('/', [ResultsPageController::class, 'index'])->name('index');
        Route::get('/show', [ResultsPageController::class, 'show'])->name('show');
        Route::get('/term', [ResultsPageController::class, 'term'])->name('term');
        Route::get('/transcript', [ResultsPageController::class, 'transcript'])->name('transcript');
        Route::get('/sheet', [TermResultSheetController::class, 'show'])->name('sheet');
        Route::post('/sheet/calculate', [TermResultSheetController::class, 'calculate'])->name('sheet.calculate');
        Route::post('/sheet/finalize', [TermResultSheetController::class, 'finalize'])->name('sheet.finalize');
        Route::post('/sheet/publish', [TermResultSheetController::class, 'publish'])->name('sheet.publish');
        Route::get('/year-sheet', [YearResultSheetController::class, 'show'])->name('year-sheet');
        Route::post('/year-sheet/calculate', [YearResultSheetController::class, 'calculate'])->name('year-sheet.calculate');
        Route::post('/year-sheet/finalize', [YearResultSheetController::class, 'finalize'])->name('year-sheet.finalize');
        Route::post('/year-sheet/publish', [YearResultSheetController::class, 'publish'])->name('year-sheet.publish');
        Route::post('/year-sheet/publications/{publication}/withdraw', [YearResultSheetController::class, 'withdraw'])->whereNumber('publication')->name('year-sheet.withdraw');
        Route::post('/year-sheet/ranking', [YearResultSheetController::class, 'ranking'])->name('year-sheet.ranking');
        Route::post('/year-sheet/transcripts', [YearResultSheetController::class, 'transcripts'])->name('year-sheet.transcripts');
    });

    Route::prefix('exams')->name('exams.')->middleware('require.school.context')->group(function (): void {
        Route::get('/', [ExamPageController::class, 'index'])->name('index');
        Route::get('/types', [ExamTypeController::class, 'index'])->name('types.index');
        Route::post('/types', [ExamTypeController::class, 'store'])->name('types.store');
        Route::post('/types/{type}', [ExamTypeController::class, 'update'])->whereNumber('type')->name('types.update');
        Route::get('/{exam}', [ExamPageController::class, 'show'])->whereNumber('exam')->name('show');
        Route::post('/', [ExamAdministrationController::class, 'store'])->name('store');
        Route::post('/{exam}/sessions', [ExamAdministrationController::class, 'storeSession'])
            ->whereNumber('exam')
            ->name('sessions.store');
        Route::post('/{exam}/update', [ExamAdministrationController::class, 'update'])->whereNumber('exam')->name('update');
        Route::post('/{exam}/cancel', [ExamAdministrationController::class, 'cancel'])->whereNumber('exam')->name('cancel');
        Route::post('/{exam}/sessions/{session}/update', [ExamAdministrationController::class, 'updateSession'])
            ->whereNumber(['exam', 'session'])
            ->name('sessions.update');
        Route::post('/{exam}/sessions/{session}/cancel', [ExamAdministrationController::class, 'cancelSession'])
            ->whereNumber(['exam', 'session'])
            ->name('sessions.cancel');
        Route::post('/{exam}/sessions/{session}/open', [ExamAdministrationController::class, 'openSession'])
            ->whereNumber(['exam', 'session'])
            ->name('sessions.open');
        Route::post('/{exam}/sessions/{session}/close', [ExamAdministrationController::class, 'closeSession'])
            ->whereNumber(['exam', 'session'])
            ->name('sessions.close');
        Route::post('/{exam}/sessions/{session}/seat', [ExamSeatingController::class, 'store'])
            ->whereNumber(['exam', 'session'])
            ->name('sessions.seat');
        Route::get('/{exam}/sessions/{session}', [ExamRosterController::class, 'show'])
            ->whereNumber(['exam', 'session'])
            ->name('sessions.show');
        Route::post('/{exam}/sessions/{session}/seats/{seat}', [ExamRosterController::class, 'update'])
            ->whereNumber(['exam', 'session', 'seat'])
            ->name('sessions.seats.update');
        Route::post('/{exam}/sessions/{session}/seats/{seat}/present', [ExamRosterController::class, 'present'])
            ->whereNumber(['exam', 'session', 'seat'])
            ->name('sessions.seats.present');
        Route::post('/{exam}/sessions/{session}/seats/{seat}/cancel', [ExamRosterController::class, 'cancel'])
            ->whereNumber(['exam', 'session', 'seat'])
            ->name('sessions.seats.cancel');
        Route::post('/{exam}/sessions/{session}/seats/{seat}/reopen', [ExamRosterController::class, 'reopen'])
            ->whereNumber(['exam', 'session', 'seat'])
            ->name('sessions.seats.reopen');
    });

    Route::prefix('grades')->name('grades.')->middleware('require.school.context')->group(function (): void {
        Route::get('/', [GradesPageController::class, 'index'])->name('index');
        Route::get('/enter', [GradesPageController::class, 'enterForm'])->name('enter');
        Route::get('/sheet', [GradeSheetController::class, 'show'])->name('sheet');
        Route::post('/sheet/{session}', [GradeSheetController::class, 'store'])->whereNumber('session')->name('sheet.store');
        Route::post('/', [GradesPageController::class, 'store'])->name('store');
        Route::get('/actions', [GradesPageController::class, 'actions'])->name('actions');
        Route::get('/reviews', [GradeReviewController::class, 'index'])->name('reviews');
        Route::post('/reviews', [GradeReviewController::class, 'store'])->name('reviews.store');
        Route::post('/reviews/{review}/start', [GradeReviewController::class, 'start'])->whereNumber('review')->name('reviews.start');
        Route::post('/reviews/{review}/decide', [GradeReviewController::class, 'decide'])->whereNumber('review')->name('reviews.decide');
        Route::post('/reviews/{review}/withdraw', [GradeReviewController::class, 'withdraw'])->whereNumber('review')->name('reviews.withdraw');
        Route::post('/{grade}/correct', [GradesPageController::class, 'correct'])->name('correct');
        Route::post('/{grade}/void', [GradesPageController::class, 'void'])->name('void');
        Route::post('/{grade}/finalize', [GradesPageController::class, 'finalize'])->name('finalize');
    });

    Route::prefix('reports')->name('reports.')->middleware('require.school.context')->group(function (): void {
        Route::get('/', [ReportsPageController::class, 'index'])->name('index');
        Route::get('/attendance-daily', [ReportsPageController::class, 'attendanceDaily'])->name('attendance-daily');
        Route::get('/enrollment-roster', [ReportsPageController::class, 'enrollmentRoster'])->name('enrollment-roster');
    });

    // «الإجازات والغياب» — staff leave: dashboard, requests + approvals, balances, types & policies, calendar, reports.
    // Student leave stays in attendance («الأعذار»); holidays in «الاجازات»; lesson cover through the timetable.
    Route::prefix('leave')->name('leave.')->middleware('require.school.context')->group(function (): void {
        Route::get('/', [LeavePageController::class, 'dashboard'])->name('dashboard');
        Route::get('/overview', [LeavePageController::class, 'overview'])->name('overview');
        Route::get('/requests', [LeavePageController::class, 'requests'])->name('requests.index');
        Route::post('/requests', [LeaveRequestController::class, 'store'])->name('requests.store');
        Route::patch('/requests/{leaveRequest}', [LeaveRequestController::class, 'update'])->whereNumber('leaveRequest')->name('requests.update');
        Route::post('/requests/{leaveRequest}/submit', [LeaveRequestController::class, 'submit'])->whereNumber('leaveRequest')->name('requests.submit');
        Route::post('/requests/{leaveRequest}/decide', [LeaveRequestController::class, 'decide'])->whereNumber('leaveRequest')->name('requests.decide');
        Route::post('/requests/{leaveRequest}/withdraw', [LeaveRequestController::class, 'withdraw'])->whereNumber('leaveRequest')->name('requests.withdraw');
        Route::post('/requests/{leaveRequest}/cancel', [LeaveRequestController::class, 'cancel'])->whereNumber('leaveRequest')->name('requests.cancel');
        Route::post('/requests/{leaveRequest}/attachments', [LeaveRequestController::class, 'attach'])->whereNumber('leaveRequest')->middleware('throttle:20,1')->name('requests.attach');
        Route::get('/requests/{leaveRequest}/attachments/{document}', [LeavePageController::class, 'attachment'])->whereNumber(['leaveRequest', 'document'])->name('requests.attachment');
        // «العطل» — the school calendar's holidays (a holiday is taken out of the calendar, never deleted).
        Route::get('/holidays', [HolidayPageController::class, 'index'])->name('holidays.index');
        Route::post('/holidays', [HolidayPageController::class, 'store'])->name('holidays.store');
        Route::patch('/holidays/{holiday}', [HolidayPageController::class, 'update'])->whereNumber('holiday')->name('holidays.update');
        Route::post('/holidays/{holiday}/status', [HolidayPageController::class, 'changeStatus'])->whereNumber('holiday')->name('holidays.status');
        Route::get('/delegations', [LeaveDelegationController::class, 'index'])->name('delegations.index');
        Route::post('/delegations', [LeaveDelegationController::class, 'store'])->name('delegations.store');
        Route::post('/delegations/{delegation}/revoke', [LeaveDelegationController::class, 'revoke'])->whereNumber('delegation')->name('delegations.revoke');
        Route::get('/notifications', [LeavePageController::class, 'notifications'])->name('notifications.index');
        Route::post('/notifications/read', [LeaveNotificationController::class, 'read'])->name('notifications.read');
        Route::get('/balances', [LeavePageController::class, 'balances'])->name('balances.index');
        Route::post('/balances/adjust', [LeaveSettingsController::class, 'adjust'])->name('balances.adjust');
        Route::post('/balances/grant', [LeaveSettingsController::class, 'grant'])->name('balances.grant');
        Route::post('/balances/carry-over', [LeaveSettingsController::class, 'carryOver'])->name('balances.carryover');
        Route::get('/types', [LeavePageController::class, 'types'])->name('types.index');
        Route::post('/types', [LeaveSettingsController::class, 'storeType'])->name('types.store');
        Route::post('/types/suggested', [LeaveSettingsController::class, 'addSuggested'])->name('types.suggested');
        Route::patch('/types/{type}', [LeaveSettingsController::class, 'updateType'])->whereNumber('type')->name('types.update');
        Route::post('/types/{type}/status', [LeaveSettingsController::class, 'typeStatus'])->whereNumber('type')->name('types.status');
        Route::post('/types/{type}/policy', [LeaveSettingsController::class, 'publishPolicy'])->whereNumber('type')->name('types.policy');
        Route::get('/calendar', [LeavePageController::class, 'calendar'])->name('calendar');
        Route::get('/reports', [LeavePageController::class, 'reports'])->name('reports');
        Route::get('/reports/export', [LeavePageController::class, 'exportReport'])->name('reports.export');
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
