<?php

namespace App\Http\Controllers\Api;

use App\Application\Timetable\DTOs\PeriodDTO;
use App\Application\Timetable\Queries\GetPeriodHandler;
use App\Application\Timetable\Queries\GetPeriodQuery;
use App\Application\Timetable\Queries\ListPeriodsHandler;
use App\Application\Timetable\Queries\ListPeriodsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Timetable\ListPeriodsRequest;
use App\Http\Requests\Timetable\ShowPeriodRequest;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\JsonResponse;

class PeriodController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function index(
        ListPeriodsRequest $request,
        ListPeriodsHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $items = $handler->handle(new ListPeriodsQuery($schoolId));

        $this->securityAudit->record(
            SecurityEventType::TimetableDataAccess,
            'timetable.periods.index',
            'viewed',
            $request->user(),
            'periods',
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(static fn (PeriodDTO $dto): array => [
                'id' => $dto->id,
                'school_id' => $dto->schoolId,
                'period_number' => $dto->periodNumber,
                'start_time' => $dto->startTime,
                'end_time' => $dto->endTime,
                'period_type' => $dto->periodType,
            ], $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function show(
        ShowPeriodRequest $request,
        int $period,
        GetPeriodHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $dto = $handler->handle(new GetPeriodQuery($schoolId, $period));

        if ($dto === null) {
            return response()->json([
                'message' => 'Period not found.',
                'error_code' => 'timetable.period_not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $this->securityAudit->record(
            SecurityEventType::TimetableDataAccess,
            'timetable.periods.show',
            'viewed',
            $request->user(),
            'period:'.$period,
            [],
        );

        return response()->json([
            'data' => [
                'id' => $dto->id,
                'school_id' => $dto->schoolId,
                'period_number' => $dto->periodNumber,
                'start_time' => $dto->startTime,
                'end_time' => $dto->endTime,
                'period_type' => $dto->periodType,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }
}
