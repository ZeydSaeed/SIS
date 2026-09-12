<?php

namespace App\Http\Controllers\Api;

use App\Application\Timetable\Commands\CancelScheduleCommand;
use App\Application\Timetable\Commands\CancelScheduleHandler;
use App\Application\Timetable\Commands\CreateScheduleCommand;
use App\Application\Timetable\Commands\CreateScheduleExceptionCommand;
use App\Application\Timetable\Commands\CreateScheduleExceptionHandler;
use App\Application\Timetable\Commands\CreateScheduleHandler;
use App\Application\Timetable\Commands\UpdateScheduleCommand;
use App\Application\Timetable\Commands\UpdateScheduleExceptionCommand;
use App\Application\Timetable\Commands\UpdateScheduleExceptionHandler;
use App\Application\Timetable\Commands\UpdateScheduleHandler;
use App\Application\Timetable\DTOs\ScheduleDTO;
use App\Application\Timetable\Queries\GetScheduleHandler;
use App\Application\Timetable\Queries\GetScheduleQuery;
use App\Application\Timetable\Queries\ListSchedulesHandler;
use App\Application\Timetable\Queries\ListSchedulesQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Timetable\CancelScheduleRequest;
use App\Http\Requests\Timetable\CreateScheduleExceptionRequest;
use App\Http\Requests\Timetable\CreateScheduleRequest;
use App\Http\Requests\Timetable\ListSchedulesRequest;
use App\Http\Requests\Timetable\ShowScheduleRequest;
use App\Http\Requests\Timetable\UpdateScheduleExceptionRequest;
use App\Http\Requests\Timetable\UpdateScheduleRequest;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\JsonResponse;

class ScheduleController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function index(
        ListSchedulesRequest $request,
        ListSchedulesHandler $handler,
    ): JsonResponse {
        $page = $handler->handle(new ListSchedulesQuery(
            schoolId: $this->schoolContext->requireId(),
            academicYearId: (int) $request->validated('academic_year_id'),
            sectionId: $request->validated('section_id') !== null
                ? (int) $request->validated('section_id')
                : null,
            lifecycleStatus: $request->validated('lifecycle_status') !== null
                ? (int) $request->validated('lifecycle_status')
                : null,
            page: (int) ($request->validated('page') ?? 1),
            perPage: (int) ($request->validated('per_page') ?? 50),
        ));

        $this->securityAudit->record(
            SecurityEventType::TimetableDataAccess,
            'timetable.schedules.index',
            'viewed',
            $request->user(),
            'schedules',
            ['academic_year_id' => (int) $request->validated('academic_year_id')],
        );

        return response()->json([
            'data' => array_map(fn (ScheduleDTO $s): array => $this->schedulePayload($s), $page->items),
            'meta' => [
                'pagination' => $page->pagination,
                'correlation_id' => CorrelationContext::id(),
            ],
        ]);
    }

    public function show(
        ShowScheduleRequest $request,
        int $schedule,
        GetScheduleHandler $handler,
    ): JsonResponse {
        $dto = $handler->handle(new GetScheduleQuery(
            schoolId: $this->schoolContext->requireId(),
            scheduleId: $schedule,
        ));

        if ($dto === null) {
            return response()->json([
                'message' => 'Schedule not found.',
                'error_code' => 'timetable.schedule_not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $this->securityAudit->record(
            SecurityEventType::TimetableDataAccess,
            'timetable.schedules.show',
            'viewed',
            $request->user(),
            'schedule:'.$dto->id,
            [],
        );

        return response()->json([
            'data' => $this->schedulePayload($dto),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function store(
        CreateScheduleRequest $request,
        CreateScheduleHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $idempotencyKey = trim((string) $request->header('X-Idempotency-Key'));

        $result = $handler->handle(new CreateScheduleCommand(
            schoolId: $schoolId,
            sectionId: (int) $request->validated('section_id'),
            academicYearId: (int) $request->validated('academic_year_id'),
            dayOfWeek: (int) $request->validated('day_of_week'),
            periodId: (int) $request->validated('period_id'),
            subjectId: (int) $request->validated('subject_id'),
            teacherId: (int) $request->validated('teacher_id'),
            idempotencyKey: $idempotencyKey,
            roomId: $request->validated('room_id') !== null
                ? (int) $request->validated('room_id')
                : null,
            createdBy: $request->user()?->id,
            correlationId: CorrelationContext::id(),
        ));

        $this->securityAudit->record(
            SecurityEventType::TimetableDataModified,
            'timetable.schedules.store',
            'created',
            $request->user(),
            'schedule:'.($result->scheduleId ?? 'unknown'),
            ['academic_year_id' => (int) $request->validated('academic_year_id')],
        );

        return response()->json([
            'data' => ['id' => $result->scheduleId],
            'meta' => [
                'from_idempotency_cache' => $result->fromIdempotencyCache,
                'correlation_id' => CorrelationContext::id(),
            ],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function update(
        UpdateScheduleRequest $request,
        int $schedule,
        UpdateScheduleHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $idempotencyKey = trim((string) $request->header('X-Idempotency-Key'));

        $result = $handler->handle(new UpdateScheduleCommand(
            schoolId: $schoolId,
            scheduleId: $schedule,
            sectionId: (int) $request->validated('section_id'),
            academicYearId: (int) $request->validated('academic_year_id'),
            dayOfWeek: (int) $request->validated('day_of_week'),
            periodId: (int) $request->validated('period_id'),
            subjectId: (int) $request->validated('subject_id'),
            teacherId: (int) $request->validated('teacher_id'),
            idempotencyKey: $idempotencyKey,
            roomId: $request->validated('room_id') !== null
                ? (int) $request->validated('room_id')
                : null,
            createdBy: $request->user()?->id,
            correlationId: CorrelationContext::id(),
        ));

        $this->securityAudit->record(
            SecurityEventType::TimetableDataModified,
            'timetable.schedules.update',
            'updated',
            $request->user(),
            'schedule:'.($result->scheduleId ?? 'unknown'),
            ['academic_year_id' => (int) $request->validated('academic_year_id')],
        );

        return response()->json([
            'data' => ['id' => $result->scheduleId],
            'meta' => [
                'from_idempotency_cache' => $result->fromIdempotencyCache,
                'correlation_id' => CorrelationContext::id(),
            ],
        ]);
    }

    public function cancel(
        CancelScheduleRequest $request,
        int $schedule,
        CancelScheduleHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $idempotencyKey = trim((string) $request->header('X-Idempotency-Key'));

        $result = $handler->handle(new CancelScheduleCommand(
            schoolId: $schoolId,
            scheduleId: $schedule,
            idempotencyKey: $idempotencyKey,
            cancelledBy: $request->user()?->id,
            correlationId: CorrelationContext::id(),
        ));

        $this->securityAudit->record(
            SecurityEventType::TimetableDataModified,
            'timetable.schedules.cancel',
            'cancelled',
            $request->user(),
            'schedule:'.($result->scheduleId ?? 'unknown'),
            [],
        );

        return response()->json([
            'data' => ['id' => $result->scheduleId],
            'meta' => [
                'from_idempotency_cache' => $result->fromIdempotencyCache,
                'correlation_id' => CorrelationContext::id(),
            ],
        ]);
    }

    public function storeException(
        CreateScheduleExceptionRequest $request,
        int $schedule,
        CreateScheduleExceptionHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $idempotencyKey = trim((string) $request->header('X-Idempotency-Key'));

        $result = $handler->handle(new CreateScheduleExceptionCommand(
            schoolId: $schoolId,
            scheduleId: $schedule,
            exceptionDate: (string) $request->validated('exception_date'),
            idempotencyKey: $idempotencyKey,
            substituteTeacherId: $request->validated('substitute_teacher_id') !== null
                ? (int) $request->validated('substitute_teacher_id')
                : null,
            substituteRoomId: $request->validated('substitute_room_id') !== null
                ? (int) $request->validated('substitute_room_id')
                : null,
            reason: $request->validated('reason'),
            createdBy: $request->user()?->id,
            correlationId: CorrelationContext::id(),
        ));

        $this->securityAudit->record(
            SecurityEventType::TimetableDataModified,
            'timetable.schedule_exceptions.store',
            'created',
            $request->user(),
            'schedule_exception:'.($result->exceptionId ?? 'unknown'),
            ['schedule_id' => $schedule],
        );

        return response()->json([
            'data' => ['id' => $result->exceptionId],
            'meta' => [
                'from_idempotency_cache' => $result->fromIdempotencyCache,
                'correlation_id' => CorrelationContext::id(),
            ],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function updateException(
        UpdateScheduleExceptionRequest $request,
        int $exception,
        UpdateScheduleExceptionHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $idempotencyKey = trim((string) $request->header('X-Idempotency-Key'));

        $result = $handler->handle(new UpdateScheduleExceptionCommand(
            schoolId: $schoolId,
            exceptionId: $exception,
            scheduleId: (int) $request->validated('schedule_id'),
            exceptionDate: (string) $request->validated('exception_date'),
            idempotencyKey: $idempotencyKey,
            substituteTeacherId: $request->validated('substitute_teacher_id') !== null
                ? (int) $request->validated('substitute_teacher_id')
                : null,
            substituteRoomId: $request->validated('substitute_room_id') !== null
                ? (int) $request->validated('substitute_room_id')
                : null,
            reason: $request->validated('reason'),
            createdBy: $request->user()?->id,
            correlationId: CorrelationContext::id(),
        ));

        $this->securityAudit->record(
            SecurityEventType::TimetableDataModified,
            'timetable.schedule_exceptions.update',
            'updated',
            $request->user(),
            'schedule_exception:'.($result->exceptionId ?? 'unknown'),
            ['schedule_id' => (int) $request->validated('schedule_id')],
        );

        return response()->json([
            'data' => ['id' => $result->exceptionId],
            'meta' => [
                'from_idempotency_cache' => $result->fromIdempotencyCache,
                'correlation_id' => CorrelationContext::id(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function schedulePayload(ScheduleDTO $dto): array
    {
        return [
            'id' => $dto->id,
            'school_id' => $dto->schoolId,
            'section_id' => $dto->sectionId,
            'academic_year_id' => $dto->academicYearId,
            'day_of_week' => $dto->dayOfWeek,
            'period_id' => $dto->periodId,
            'subject_id' => $dto->subjectId,
            'teacher_id' => $dto->teacherId,
            'room_id' => $dto->roomId,
            'lifecycle_status' => $dto->lifecycleStatus,
        ];
    }
}
