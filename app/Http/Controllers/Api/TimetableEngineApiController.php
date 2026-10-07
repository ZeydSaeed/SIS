<?php

namespace App\Http\Controllers\Api;

use App\Application\Timetable\Queries\GetEffectiveTimetableHandler;
use App\Application\Timetable\Queries\GetEffectiveTimetableQuery;
use App\Application\Timetable\Queries\GetStudentTimetableHandler;
use App\Application\Timetable\Queries\GetStudentTimetableQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Timetable\ReadEffectiveTimetableRequest;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\JsonResponse;

/**
 * Timetable read API for other modules (spec §87, §93): the effective timetable of a date — what Attendance
 * needs instead of re-entering teacher / subject / room / group — and a student's week.
 */
final class TimetableEngineApiController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function effective(ReadEffectiveTimetableRequest $request, GetEffectiveTimetableHandler $handler): JsonResponse
    {
        $dto = $handler->handle(new GetEffectiveTimetableQuery(
            $this->schoolContext->requireId(),
            (int) $request->validated('academic_year_id'),
            (string) ($request->validated('date') ?? (new \DateTimeImmutable)->format('Y-m-d')),
            self::int($request->validated('section_id')),
            self::int($request->validated('teacher_id')),
        ));
        $this->securityAudit->record(SecurityEventType::TimetableDataAccess, 'timetable.effective.show', 'viewed', $request->user(), 'effective', ['count' => count($dto?->lessons ?? [])]);

        return response()->json(['data' => $dto?->lessons ?? [], 'meta' => ($dto?->meta ?? []) + ['correlation_id' => CorrelationContext::id()]]);
    }

    public function student(int $student, ReadEffectiveTimetableRequest $request, GetStudentTimetableHandler $handler): JsonResponse
    {
        $dto = $handler->handle(new GetStudentTimetableQuery(
            $this->schoolContext->requireId(),
            (int) $request->validated('academic_year_id'),
            $student,
            $request->validated('date'),
        ));
        if ($dto === null) {
            return response()->json(['message' => 'Student has no active enrollment.', 'error_code' => 'timetable.student_not_enrolled', 'meta' => ['correlation_id' => CorrelationContext::id()]], 404);
        }
        $this->securityAudit->record(SecurityEventType::TimetableDataAccess, 'timetable.students.show', 'viewed', $request->user(), 'student:'.$student, []);

        return response()->json(['data' => $dto->lessons, 'meta' => $dto->meta + ['correlation_id' => CorrelationContext::id()]]);
    }

    private static function int(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
