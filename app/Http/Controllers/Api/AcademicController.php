<?php

namespace App\Http\Controllers\Api;

use App\Application\Academic\Commands\CreateAcademicYearCommand;
use App\Application\Academic\Commands\CreateAcademicYearHandler;
use App\Application\Academic\DTOs\AcademicYearDTO;
use App\Application\Academic\DTOs\GradeLevelDTO;
use App\Application\Academic\DTOs\HolidayDTO;
use App\Application\Academic\DTOs\TermDTO;
use App\Application\Academic\Queries\GetAcademicYearHandler;
use App\Application\Academic\Queries\GetAcademicYearQuery;
use App\Application\Academic\Queries\GetGradeLevelHandler;
use App\Application\Academic\Queries\GetGradeLevelQuery;
use App\Application\Academic\Queries\GetTermHandler;
use App\Application\Academic\Queries\GetTermQuery;
use App\Application\Academic\Queries\ListAcademicYearsHandler;
use App\Application\Academic\Queries\ListAcademicYearsQuery;
use App\Application\Academic\Queries\ListGradeLevelsHandler;
use App\Application\Academic\Queries\ListGradeLevelsQuery;
use App\Application\Academic\Queries\ListHolidaysHandler;
use App\Application\Academic\Queries\ListHolidaysQuery;
use App\Application\Academic\Queries\ListTermsHandler;
use App\Application\Academic\Queries\ListTermsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\CreateAcademicYearRequest;
use App\Http\Requests\Academic\ListAcademicYearsRequest;
use App\Http\Requests\Academic\ListGradeLevelsRequest;
use App\Http\Requests\Academic\ListHolidaysRequest;
use App\Http\Requests\Academic\ListTermsRequest;
use App\Http\Requests\Academic\ShowAcademicYearRequest;
use App\Http\Requests\Academic\ShowGradeLevelRequest;
use App\Http\Requests\Academic\ShowTermRequest;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\JsonResponse;

class AcademicController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function indexYears(
        ListAcademicYearsRequest $request,
        ListAcademicYearsHandler $handler,
    ): JsonResponse {
        $this->schoolContext->requireId();
        $items = $handler->handle(new ListAcademicYearsQuery);

        $this->securityAudit->record(
            SecurityEventType::AcademicDataAccess,
            'academic.years.index',
            'viewed',
            $request->user(),
            'academic_years',
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(fn (AcademicYearDTO $dto): array => $this->yearPayload($dto), $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function showYear(
        ShowAcademicYearRequest $request,
        int $year,
        GetAcademicYearHandler $handler,
    ): JsonResponse {
        $this->schoolContext->requireId();
        $dto = $handler->handle(new GetAcademicYearQuery(academicYearId: $year));

        if ($dto === null) {
            return response()->json([
                'message' => 'Academic year not found.',
                'error_code' => 'academic.year_not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $this->securityAudit->record(
            SecurityEventType::AcademicDataAccess,
            'academic.years.show',
            'viewed',
            $request->user(),
            'academic_year:'.$year,
            [],
        );

        return response()->json([
            'data' => $this->yearPayload($dto),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function storeYear(
        CreateAcademicYearRequest $request,
        CreateAcademicYearHandler $handler,
    ): JsonResponse {
        $this->schoolContext->requireId();
        $result = $handler->handle(new CreateAcademicYearCommand(
            code: (string) $request->validated('code'),
            name: (string) $request->validated('name'),
            startDate: (string) $request->validated('start_date'),
            endDate: (string) $request->validated('end_date'),
            isCurrent: (bool) $request->boolean('is_current'),
            status: (int) ($request->validated('status') ?? 1),
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
        ));

        $this->securityAudit->record(
            SecurityEventType::AcademicDataModified,
            'academic.years.create',
            'created',
            $request->user(),
            'academic_year:'.$result->academicYearId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'academic_year_id' => $result->academicYearId,
                'code' => $result->code,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function indexGradeLevels(
        ListGradeLevelsRequest $request,
        ListGradeLevelsHandler $handler,
    ): JsonResponse {
        $this->schoolContext->requireId();
        $items = $handler->handle(new ListGradeLevelsQuery);

        $this->securityAudit->record(
            SecurityEventType::AcademicDataAccess,
            'academic.grade_levels.index',
            'viewed',
            $request->user(),
            'grade_levels',
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(fn (GradeLevelDTO $dto): array => $this->gradeLevelPayload($dto), $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function showGradeLevel(
        ShowGradeLevelRequest $request,
        int $gradeLevel,
        GetGradeLevelHandler $handler,
    ): JsonResponse {
        $this->schoolContext->requireId();
        $dto = $handler->handle(new GetGradeLevelQuery(gradeLevelId: $gradeLevel));

        if ($dto === null) {
            return response()->json([
                'message' => 'Grade level not found.',
                'error_code' => 'academic.grade_level_not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $this->securityAudit->record(
            SecurityEventType::AcademicDataAccess,
            'academic.grade_levels.show',
            'viewed',
            $request->user(),
            'grade_level:'.$gradeLevel,
            [],
        );

        return response()->json([
            'data' => $this->gradeLevelPayload($dto),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function indexTerms(
        ListTermsRequest $request,
        ListTermsHandler $handler,
    ): JsonResponse {
        $this->schoolContext->requireId();
        $academicYearId = $request->validated('academic_year_id');
        $items = $handler->handle(new ListTermsQuery(
            academicYearId: $academicYearId !== null ? (int) $academicYearId : null,
        ));

        $this->securityAudit->record(
            SecurityEventType::AcademicDataAccess,
            'academic.terms.index',
            'viewed',
            $request->user(),
            'terms',
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(fn (TermDTO $dto): array => $this->termPayload($dto), $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function showTerm(
        ShowTermRequest $request,
        int $term,
        GetTermHandler $handler,
    ): JsonResponse {
        $this->schoolContext->requireId();
        $dto = $handler->handle(new GetTermQuery(termId: $term));

        if ($dto === null) {
            return response()->json([
                'message' => 'Term not found.',
                'error_code' => 'academic.term_not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $this->securityAudit->record(
            SecurityEventType::AcademicDataAccess,
            'academic.terms.show',
            'viewed',
            $request->user(),
            'term:'.$term,
            [],
        );

        return response()->json([
            'data' => $this->termPayload($dto),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function indexHolidays(
        ListHolidaysRequest $request,
        ListHolidaysHandler $handler,
    ): JsonResponse {
        $academicYearId = $request->validated('academic_year_id');
        $items = $handler->handle(new ListHolidaysQuery(
            schoolId: $this->schoolContext->requireId(),
            academicYearId: $academicYearId !== null ? (int) $academicYearId : null,
        ));

        $this->securityAudit->record(
            SecurityEventType::AcademicDataAccess,
            'academic.holidays.index',
            'viewed',
            $request->user(),
            'holidays',
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(static fn (HolidayDTO $dto): array => [
                'id' => $dto->id,
                'academic_year_id' => $dto->academicYearId,
                'school_id' => $dto->schoolId,
                'name' => $dto->name,
                'start_date' => $dto->startDate,
                'end_date' => $dto->endDate,
                'holiday_type' => $dto->holidayType,
                'created_at' => $dto->createdAt,
            ], $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    /** @return array<string, mixed> */
    private function yearPayload(AcademicYearDTO $dto): array
    {
        return [
            'id' => $dto->id,
            'code' => $dto->code,
            'name' => $dto->name,
            'start_date' => $dto->startDate,
            'end_date' => $dto->endDate,
            'is_current' => $dto->isCurrent,
            'status' => $dto->status,
            'created_at' => $dto->createdAt,
            'updated_at' => $dto->updatedAt,
        ];
    }

    /** @return array<string, mixed> */
    private function gradeLevelPayload(GradeLevelDTO $dto): array
    {
        return [
            'id' => $dto->id,
            'code' => $dto->code,
            'name' => $dto->name,
            'level_order' => $dto->levelOrder,
            'education_stage' => $dto->educationStage,
            'status' => $dto->status,
        ];
    }

    /** @return array<string, mixed> */
    private function termPayload(TermDTO $dto): array
    {
        return [
            'id' => $dto->id,
            'academic_year_id' => $dto->academicYearId,
            'code' => $dto->code,
            'name' => $dto->name,
            'start_date' => $dto->startDate,
            'end_date' => $dto->endDate,
            'term_order' => $dto->termOrder,
            'status' => $dto->status,
            'created_at' => $dto->createdAt,
            'updated_at' => $dto->updatedAt,
        ];
    }
}
