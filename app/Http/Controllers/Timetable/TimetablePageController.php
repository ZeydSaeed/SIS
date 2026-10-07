<?php

namespace App\Http\Controllers\Timetable;

use App\Application\Curriculum\DTOs\SubjectDTO;
use App\Application\Curriculum\Queries\ListSubjectsHandler;
use App\Application\Curriculum\Queries\ListSubjectsQuery;
use App\Application\Enrollment\DTOs\SectionDTO;
use App\Application\Enrollment\Queries\GetClassStructureHandler;
use App\Application\Enrollment\Queries\GetClassStructureQuery;
use App\Application\Organization\Queries\GetBranchStructureHandler;
use App\Application\Organization\Queries\GetBranchStructureQuery;
use App\Application\Shared\Results\ApplicationResult;
use App\Application\Timetable\Commands\ArrangeSchoolDayCommand;
use App\Application\Timetable\Commands\ArrangeSchoolDayHandler;
use App\Application\Timetable\Commands\AutoPlaceSectionCommand;
use App\Application\Timetable\Commands\AutoPlaceSectionHandler;
use App\Application\Timetable\Commands\CancelScheduleCommand;
use App\Application\Timetable\Commands\CancelScheduleHandler;
use App\Application\Timetable\Commands\CreatePeriodCommand;
use App\Application\Timetable\Commands\CreatePeriodHandler;
use App\Application\Timetable\Commands\CreateScheduleCommand;
use App\Application\Timetable\Commands\CreateScheduleExceptionCommand;
use App\Application\Timetable\Commands\CreateScheduleExceptionHandler;
use App\Application\Timetable\Commands\CreateScheduleHandler;
use App\Application\Timetable\Commands\ShiftScheduleCommand;
use App\Application\Timetable\Commands\ShiftScheduleHandler;
use App\Application\Timetable\Commands\SwapSchedulesCommand;
use App\Application\Timetable\Commands\SwapSchedulesHandler;
use App\Application\Timetable\Commands\UpdatePeriodCommand;
use App\Application\Timetable\Commands\UpdatePeriodHandler;
use App\Application\Timetable\Commands\UpdateScheduleCommand;
use App\Application\Timetable\Commands\UpdateScheduleHandler;
use App\Application\Timetable\DTOs\PeriodDTO;
use App\Application\Timetable\DTOs\TimetableEngineDTO;
use App\Application\Timetable\DTOs\TimetableInsightDTO;
use App\Application\Timetable\Queries\CompareTimetableVersionsHandler;
use App\Application\Timetable\Queries\CompareTimetableVersionsQuery;
use App\Application\Timetable\Queries\GetGenerationRunHandler;
use App\Application\Timetable\Queries\GetGenerationRunQuery;
use App\Application\Timetable\Queries\GetScheduleHandler;
use App\Application\Timetable\Queries\GetScheduleQuery;
use App\Application\Timetable\Queries\GetStudentTimetableHandler;
use App\Application\Timetable\Queries\GetStudentTimetableQuery;
use App\Application\Timetable\Queries\GetTimetableEngineHandler;
use App\Application\Timetable\Queries\GetTimetableEngineQuery;
use App\Application\Timetable\Queries\GetTimetableVersionEntriesHandler;
use App\Application\Timetable\Queries\GetTimetableVersionEntriesQuery;
use App\Application\Timetable\Queries\GetTimetableWorkspaceHandler;
use App\Application\Timetable\Queries\GetTimetableWorkspaceQuery;
use App\Application\Timetable\Queries\SuggestScheduleMovesHandler;
use App\Application\Timetable\Queries\SuggestScheduleMovesQuery;
use App\Application\Timetable\Queries\SuggestSubstitutesHandler;
use App\Application\Timetable\Queries\SuggestSubstitutesQuery;
use App\Application\Timetable\Support\TimetableCsvExporter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Timetable\ArrangeSchoolDayRequest;
use App\Http\Requests\Timetable\AssignSubstituteRequest;
use App\Http\Requests\Timetable\AutoPlaceSectionRequest;
use App\Http\Requests\Timetable\CancelScheduleRequest;
use App\Http\Requests\Timetable\CreateScheduleRequest;
use App\Http\Requests\Timetable\SavePeriodRequest;
use App\Http\Requests\Timetable\ShiftScheduleRequest;
use App\Http\Requests\Timetable\SwapSchedulesRequest;
use App\Http\Requests\Timetable\UpdateScheduleRequest;
use App\Http\Support\AcademicYearContextResolver;
use App\Infrastructure\Persistence\Eloquent\ScheduleRecord;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** «الجدول الدراسي» builder: the school day, sections × periods grid and lessons to place. */
final class TimetablePageController extends Controller
{
    private const ACTIVE = 1;

    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly AcademicYearContextResolver $academicYears,
    ) {}

    public function index(
        Request $request,
        GetTimetableWorkspaceHandler $workspace,
        GetClassStructureHandler $classes,
        GetBranchStructureHandler $branches,
        ListSubjectsHandler $subjects,
        GetTimetableEngineHandler $engineHandler,
        GetTimetableVersionEntriesHandler $versionEntries,
        GetGenerationRunHandler $runs,
        CompareTimetableVersionsHandler $compare,
        SuggestScheduleMovesHandler $moves,
        SuggestSubstitutesHandler $substitutes,
    ): Response {
        $this->authorize('view', ScheduleRecord::class);

        $schoolId = $this->schoolContext->requireId();
        $user = $request->user();
        assert($user !== null);

        $requestedYear = $request->filled('academic_year_id')
            ? (int) $request->query('academic_year_id')
            : null;
        $academicYearId = $this->academicYears->resolve($requestedYear);

        $periods = [];
        $lessons = [];
        $schedules = [];
        $teachers = [];
        $sections = [];
        $teacherSubjects = [];
        $practicalSubjectIds = [];
        $issues = [];
        $placements = [];
        $advice = null;
        $workload = [];
        $quality = null;
        $engine = null;
        $viewingVersion = null;
        if ($academicYearId !== null) {
            $dto = $workspace->handle(new GetTimetableWorkspaceQuery($schoolId, $academicYearId));
            $periods = array_map(static fn (PeriodDTO $p): array => [
                'id' => $p->id,
                'period_number' => $p->periodNumber,
                'start_time' => substr($p->startTime, 0, 5),
                'end_time' => substr($p->endTime, 0, 5),
                'period_type' => $p->periodType,
            ], $dto->periods);
            $lessons = $dto->lessons;
            $schedules = $dto->schedules;
            $teachers = $dto->teachers;
            $teacherSubjects = $dto->teacherSubjects;
            $practicalSubjectIds = $dto->practicalSubjectIds;
            $issues = $dto->issues;
            $placements = $dto->placements;
            $advice = $dto->advice;
            $workload = $dto->workload;
            $quality = $dto->quality;
            $engine = $this->engineProps($engineHandler->handle(new GetTimetableEngineQuery($schoolId, $academicYearId), $dto->board));

            // «عرض إصدار»: a version's lessons on the grid, read-only (history).
            if ($request->filled('version')) {
                $entries = $versionEntries->handle(new GetTimetableVersionEntriesQuery($schoolId, (int) $request->query('version')));
                if ($entries !== null && $entries->meta['version']['academic_year_id'] === $academicYearId) {
                    $schedules = $entries->lessons;
                    $viewingVersion = $entries->meta['version'];
                }
            }

            $structure = $classes->handle(new GetClassStructureQuery($schoolId, $academicYearId));
            foreach ($structure->classes as $class) {
                if ($class->status !== self::ACTIVE) {
                    continue;
                }
                foreach ($structure->sectionsByClass[$class->id] ?? [] as $section) {
                    if ($section->status !== self::ACTIVE) {
                        continue;
                    }
                    $sections[] = self::sectionRow($section, $class->id, $class->name);
                }
            }
        }

        $this->securityAudit->record(
            SecurityEventType::TimetableDataAccess,
            'timetable.web.index',
            'viewed',
            $user,
            'schedules',
            ['academic_year_id' => $academicYearId, 'schedules' => count($schedules)],
        );

        return Inertia::render('timetable/index', [
            'periods' => $periods,
            'sections' => $sections,
            'lessons' => $lessons,
            'schedules' => $schedules,
            'teachers' => $teachers,
            'teacherSubjects' => $teacherSubjects,
            'practicalSubjectIds' => $practicalSubjectIds,
            'issues' => $issues,
            'advice' => $advice,
            'workload' => $workload,
            'quality' => $quality,
            // Filters «الفرع / الاختصاص / الصف / الشعبة»: the organization + class structures (SSOT),
            // and which branch / department each section serves (students' placement).
            'branches' => array_map(
                static fn (array $b): array => [
                    'id' => $b['id'],
                    'name' => $b['name'],
                    'departments' => array_map(static fn (array $d): array => ['id' => $d['id'], 'name' => $d['name']], $b['departments']),
                ],
                $branches->handle(new GetBranchStructureQuery($schoolId)),
            ),
            'placements' => $placements,
            'subjects' => array_map(
                static fn (SubjectDTO $s): array => ['id' => $s->id, 'name' => $s->name],
                $subjects->handle(new ListSubjectsQuery),
            ),
            'filters' => ['academic_year_id' => $academicYearId],
            'engine' => $engine,
            'viewingVersion' => $viewingVersion,
            // On demand (partial reloads): a run's review, a comparison, move / substitute suggestions.
            'runDetail' => Inertia::optional(fn () => $academicYearId === null || ! $request->filled('run') ? null
                : $this->insight($runs->handle(new GetGenerationRunQuery($schoolId, (int) $request->query('run'))))),
            'comparison' => Inertia::optional(fn () => $academicYearId === null ? null : $this->insight($compare->handle(new CompareTimetableVersionsQuery(
                $schoolId, $academicYearId, self::optionalInt($request->query('compare_a')), self::optionalInt($request->query('compare_b')),
            )))),
            'moveSuggestions' => Inertia::optional(fn () => $academicYearId === null || ! $request->filled('suggest') ? null
                : $this->insight($moves->handle(new SuggestScheduleMovesQuery($schoolId, $academicYearId, (int) $request->query('suggest'))))),
            'substitutes' => Inertia::optional(fn () => $academicYearId === null || ! $request->filled('substitute') ? null
                : $this->insight($substitutes->handle(new SuggestSubstitutesQuery($schoolId, $academicYearId, (int) $request->query('substitute'), (string) $request->query('date', ''))))),
            'authorization' => [
                'can_create' => $user->can('createSchedule', ScheduleRecord::class),
                'can_update' => $user->can('updateSchedule', ScheduleRecord::class),
                'can_cancel' => $user->can('cancelSchedule', ScheduleRecord::class),
                'can_manage_periods' => $user->can('managePeriods', ScheduleRecord::class),
                'can_manage_constraints' => $user->can('manageConstraints', ScheduleRecord::class),
                'can_generate' => $user->can('generate', ScheduleRecord::class),
                'can_publish' => $user->can('publish', ScheduleRecord::class),
                'can_approve' => $user->can('approve', ScheduleRecord::class),
                'can_substitute' => $user->can('createException', ScheduleRecord::class),
            ],
        ]);
    }

    /** «بديل ليوم»: the chosen substitute becomes the lesson's exception for that date (the existing exception command). */
    public function storeException(int $schedule, AssignSubstituteRequest $request, CreateScheduleExceptionHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new CreateScheduleExceptionCommand(
            schoolId: $this->schoolContext->requireId(),
            scheduleId: $schedule,
            exceptionDate: (string) $request->validated('exception_date'),
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            substituteTeacherId: $this->nullableInt($request->validated('substitute_teacher_id')),
            substituteRoomId: $this->nullableInt($request->validated('substitute_room_id')),
            reason: $request->validated('reason'),
            createdBy: $request->user()?->id,
            correlationId: CorrelationContext::id(),
        ));

        return $this->respondSchedule($request, $result, 'timetable.web.schedules.substitute', 'created', 'schedule:'.$schedule, 'flash.timetable.engine.substituteAssigned');
    }

    /** «جدول الطالب»: one student's week from membership (section + groups), read-only. */
    public function student(Request $request, int $student, GetStudentTimetableHandler $handler, GetTimetableWorkspaceHandler $workspace, ListSubjectsHandler $subjects): Response
    {
        $this->authorize('view', ScheduleRecord::class);
        $schoolId = $this->schoolContext->requireId();
        $academicYearId = $this->academicYears->resolve($request->filled('academic_year_id') ? (int) $request->query('academic_year_id') : null);
        $dto = $academicYearId === null ? null : $handler->handle(new GetStudentTimetableQuery($schoolId, $academicYearId, $student, self::optionalDate($request->query('date'))));
        if ($dto === null) {
            abort(404);
        }
        $space = $workspace->handle(new GetTimetableWorkspaceQuery($schoolId, $academicYearId));
        $this->securityAudit->record(SecurityEventType::TimetableDataAccess, 'timetable.web.student', 'viewed', $request->user(), 'student:'.$student, ['academic_year_id' => $academicYearId]);

        return Inertia::render('timetable/student', [
            'lessons' => $dto->lessons,
            'meta' => $dto->meta,
            'periods' => array_map(static fn (PeriodDTO $p): array => ['id' => $p->id, 'period_number' => $p->periodNumber, 'start_time' => substr($p->startTime, 0, 5), 'end_time' => substr($p->endTime, 0, 5), 'period_type' => $p->periodType], $space->periods),
            'teachers' => $space->teachers,
            'subjects' => array_map(static fn (SubjectDTO $s): array => ['id' => $s->id, 'name' => $s->name], $subjects->handle(new ListSubjectsQuery)),
            'days' => $space->board?->settings->days ?? [1, 2, 3, 4, 5],
            'filters' => ['academic_year_id' => $academicYearId],
        ]);
    }

    /** «تصدير»: the timetable as CSV (UTF-8 BOM, opens in Excel) — by section or by teacher, live grid or a version. */
    public function export(
        Request $request,
        GetTimetableWorkspaceHandler $workspace,
        GetTimetableVersionEntriesHandler $versionEntries,
        GetClassStructureHandler $classes,
        ListSubjectsHandler $subjects,
        TimetableCsvExporter $csv,
    ): StreamedResponse {
        $this->authorize('view', ScheduleRecord::class);
        $schoolId = $this->schoolContext->requireId();
        $academicYearId = $this->academicYears->resolve($request->filled('academic_year_id') ? (int) $request->query('academic_year_id') : null);
        abort_if($academicYearId === null, 404);
        $dto = $workspace->handle(new GetTimetableWorkspaceQuery($schoolId, $academicYearId));
        $lessons = $dto->schedules;
        if ($request->filled('version')) {
            $lessons = $versionEntries->handle(new GetTimetableVersionEntriesQuery($schoolId, (int) $request->query('version')))?->lessons ?? abort(404);
        }
        $this->securityAudit->record(SecurityEventType::TimetableDataAccess, 'timetable.web.export', 'exported', $request->user(), 'schedules', ['academic_year_id' => $academicYearId, 'rows' => count($lessons)]);
        $sectionNames = [];
        $structure = $classes->handle(new GetClassStructureQuery($schoolId, $academicYearId));
        foreach ($structure->classes as $class) {
            foreach ($structure->sectionsByClass[$class->id] ?? [] as $section) {
                $sectionNames[$section->id] = $class->name.' — '.$section->name;
            }
        }
        $subjectNames = [];
        foreach ($subjects->handle(new ListSubjectsQuery) as $subject) {
            $subjectNames[$subject->id] = $subject->name;
        }
        $rows = $csv->rows($lessons, $dto->board, $subjectNames, array_column($dto->teachers, 'short_name', 'id'), $sectionNames,
            $request->query('by') === 'teacher' ? 'teacher' : 'section');

        return response()->streamDownload(static function () use ($rows): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'timetable-'.$academicYearId.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array<string, mixed> */
    private function engineProps(TimetableEngineDTO $engine): array
    {
        return [
            'settings' => $engine->settings, 'activities' => $engine->activities, 'groups' => $engine->groups,
            'availability' => $engine->availability, 'rules' => $engine->rules, 'catalogue' => $engine->catalogue,
            'rooms' => $engine->rooms, 'workshops' => $engine->workshops, 'runs' => $engine->runs,
            'versions' => $engine->versions, 'status' => $engine->status,
        ];
    }

    private function insight(?TimetableInsightDTO $dto): ?array
    {
        return $dto?->data;
    }

    private static function optionalInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private static function optionalDate(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }

    public function show(Request $request, int $schedule, GetScheduleHandler $handler): Response
    {
        $this->authorize('view', ScheduleRecord::class);

        $schoolId = $this->schoolContext->requireId();
        $user = $request->user();
        assert($user !== null);

        $dto = $handler->handle(new GetScheduleQuery(
            schoolId: $schoolId,
            scheduleId: $schedule,
        ));

        if ($dto === null) {
            abort(404);
        }

        $this->securityAudit->record(
            SecurityEventType::TimetableDataAccess,
            'timetable.web.show',
            'viewed',
            $user,
            'schedule:'.$schedule,
            ['academic_year_id' => $dto->academicYearId],
        );

        return Inertia::render('timetable/show', [
            'schedule' => [
                'id' => $dto->id,
                'section_id' => $dto->sectionId,
                'academic_year_id' => $dto->academicYearId,
                'day_of_week' => $dto->dayOfWeek,
                'period_id' => $dto->periodId,
                'subject_id' => $dto->subjectId,
                'teacher_id' => $dto->teacherId,
                'room_id' => $dto->roomId,
                'lifecycle_status' => $dto->lifecycleStatus,
            ],
        ]);
    }

    public function storePeriod(SavePeriodRequest $request, CreatePeriodHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new CreatePeriodCommand(
            schoolId: $this->schoolContext->requireId(),
            periodNumber: (int) $request->validated('period_number'),
            startTime: (string) $request->validated('start_time'),
            endTime: (string) $request->validated('end_time'),
            periodType: (int) $request->validated('period_type'),
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
        ));

        return $this->respondPeriod($request, $result, 'timetable.web.periods.store', 'created', 'flash.timetable.periodAdded');
    }

    public function updatePeriod(int $period, SavePeriodRequest $request, UpdatePeriodHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new UpdatePeriodCommand(
            schoolId: $this->schoolContext->requireId(),
            periodId: $period,
            periodNumber: (int) $request->validated('period_number'),
            startTime: (string) $request->validated('start_time'),
            endTime: (string) $request->validated('end_time'),
            periodType: (int) $request->validated('period_type'),
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
        ));

        return $this->respondPeriod($request, $result, 'timetable.web.periods.update', 'updated', 'flash.timetable.periodUpdated');
    }

    /** Drop a lesson card into an empty cell. Domain conflicts flash their code (bootstrap/app.php). */
    public function storeSchedule(CreateScheduleRequest $request, CreateScheduleHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new CreateScheduleCommand(
            schoolId: $this->schoolContext->requireId(),
            sectionId: (int) $request->validated('section_id'),
            academicYearId: (int) $request->validated('academic_year_id'),
            dayOfWeek: (int) $request->validated('day_of_week'),
            periodId: (int) $request->validated('period_id'),
            subjectId: (int) $request->validated('subject_id'),
            teacherId: (int) $request->validated('teacher_id'),
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            roomId: $this->nullableInt($request->validated('room_id')),
            createdBy: $request->user()?->id,
            correlationId: CorrelationContext::id(),
        ));

        $this->auditSchedule($request, 'timetable.web.schedules.store', 'created', $result->scheduleId);

        return redirect()->back();
    }

    /** Move a placed lesson to another cell. */
    public function updateSchedule(int $schedule, UpdateScheduleRequest $request, UpdateScheduleHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new UpdateScheduleCommand(
            schoolId: $this->schoolContext->requireId(),
            scheduleId: $schedule,
            sectionId: (int) $request->validated('section_id'),
            academicYearId: (int) $request->validated('academic_year_id'),
            dayOfWeek: (int) $request->validated('day_of_week'),
            periodId: (int) $request->validated('period_id'),
            subjectId: (int) $request->validated('subject_id'),
            teacherId: (int) $request->validated('teacher_id'),
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            roomId: $this->nullableInt($request->validated('room_id')),
            createdBy: $request->user()?->id,
            correlationId: CorrelationContext::id(),
        ));

        $this->auditSchedule($request, 'timetable.web.schedules.update', 'updated', $result->scheduleId);

        return redirect()->back();
    }

    /** Take a lesson off the grid (soft: lifecycle → cancelled; the card returns to the tray). */
    public function cancelSchedule(int $schedule, CancelScheduleRequest $request, CancelScheduleHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new CancelScheduleCommand(
            schoolId: $this->schoolContext->requireId(),
            scheduleId: $schedule,
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            cancelledBy: $request->user()?->id,
            correlationId: CorrelationContext::id(),
        ));

        $this->auditSchedule($request, 'timetable.web.schedules.cancel', 'cancelled', $result->scheduleId);

        return redirect()->back();
    }

    /** «استبدال حصة بأخرى»: drop a lesson on another lesson of the section. */
    public function swapSchedules(int $schedule, SwapSchedulesRequest $request, SwapSchedulesHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new SwapSchedulesCommand(
            schoolId: $this->schoolContext->requireId(),
            firstScheduleId: $schedule,
            secondScheduleId: (int) $request->validated('with_schedule_id'),
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
        ));

        return $this->respondSchedule($request, $result, 'timetable.web.schedules.swap', 'swapped', 'schedule:'.$schedule, null);
    }

    /** «زحف المادة» one period later / earlier. */
    public function shiftSchedule(int $schedule, ShiftScheduleRequest $request, ShiftScheduleHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new ShiftScheduleCommand(
            schoolId: $this->schoolContext->requireId(),
            scheduleId: $schedule,
            direction: (int) $request->validated('direction'),
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
        ));

        return $this->respondSchedule($request, $result, 'timetable.web.schedules.shift', 'shifted', 'schedule:'.$schedule, null);
    }

    /** «توزيع تلقائي» of the section's remaining lessons. */
    public function autoPlace(AutoPlaceSectionRequest $request, AutoPlaceSectionHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new AutoPlaceSectionCommand(
            schoolId: $this->schoolContext->requireId(),
            academicYearId: (int) $request->validated('academic_year_id'),
            sectionIds: array_map(intval(...), (array) $request->validated('section_ids')),
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            createdBy: $request->user()?->id,
            correlationId: CorrelationContext::id(),
        ));

        $flash = ! $result->failed() && $result->unplaced > 0 ? 'flash.timetable.autoPlacedPartly' : 'flash.timetable.autoPlaced';

        return $this->respondSchedule($request, $result, 'timetable.web.schedules.auto', 'placed', 'sections:'.count((array) $request->validated('section_ids')), $flash);
    }

    /** «توزيع الاستراحات تلقائياً»: same lessons, breaks of 5 / 10 / 15 minutes. */
    public function arrangeDay(ArrangeSchoolDayRequest $request, ArrangeSchoolDayHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new ArrangeSchoolDayCommand(
            schoolId: $this->schoolContext->requireId(),
            startTime: (string) $request->validated('start_time'),
            lessonMinutes: (int) $request->validated('lesson_minutes'),
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
        ));

        return $this->respondPeriod($request, $result, 'timetable.web.periods.arrange', 'arranged', 'flash.timetable.dayArranged');
    }

    /** @return array{id: int, class_id: int, class_name: string, code: string, name: string} */
    private static function sectionRow(SectionDTO $section, int $classId, string $className): array
    {
        return ['id' => $section->id, 'class_id' => $classId, 'class_name' => $className, 'code' => $section->code, 'name' => $section->name];
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function auditSchedule(Request $request, string $action, string $outcome, ?int $scheduleId): void
    {
        $this->securityAudit->record(
            SecurityEventType::TimetableDataModified,
            $action,
            $outcome,
            $request->user(),
            'schedule:'.($scheduleId ?? 'unknown'),
            ['academic_year_id' => $request->input('academic_year_id')],
        );
    }

    private function respondSchedule(Request $request, ApplicationResult $result, string $action, string $outcome, string $resource, ?string $flash): RedirectResponse
    {
        if ($result->failed()) {
            return redirect()->back()->withErrors(['schedule' => $result->errors[0] ?? 'timetable.schedule_slot_conflict']);
        }

        $this->securityAudit->record(
            SecurityEventType::TimetableDataModified,
            $action,
            $outcome,
            $request->user(),
            $resource,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return $flash === null ? redirect()->back() : redirect()->back()->with('success', $flash);
    }

    private function respondPeriod(Request $request, ApplicationResult $result, string $action, string $outcome, string $flash): RedirectResponse
    {
        if ($result->failed()) {
            return redirect()->back()->withErrors(['period' => $result->errors[0] ?? 'timetable.period_save_failed']);
        }

        $this->securityAudit->record(
            SecurityEventType::TimetableDataModified,
            $action,
            $outcome,
            $request->user(),
            'periods',
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return redirect()->back()->with('success', $flash);
    }
}
