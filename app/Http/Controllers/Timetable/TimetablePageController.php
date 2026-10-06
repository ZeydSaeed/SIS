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
use App\Application\Timetable\Queries\GetScheduleHandler;
use App\Application\Timetable\Queries\GetScheduleQuery;
use App\Application\Timetable\Queries\GetTimetableWorkspaceHandler;
use App\Application\Timetable\Queries\GetTimetableWorkspaceQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Timetable\ArrangeSchoolDayRequest;
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
            'authorization' => [
                'can_create' => $user->can('createSchedule', ScheduleRecord::class),
                'can_update' => $user->can('updateSchedule', ScheduleRecord::class),
                'can_cancel' => $user->can('cancelSchedule', ScheduleRecord::class),
                'can_manage_periods' => $user->can('managePeriods', ScheduleRecord::class),
            ],
        ]);
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
