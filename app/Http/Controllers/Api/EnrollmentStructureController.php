<?php

namespace App\Http\Controllers\Api;

use App\Application\Enrollment\Commands\DeactivateClassCommand;
use App\Application\Enrollment\Commands\DeactivateClassHandler;
use App\Application\Enrollment\Commands\DeactivateSectionCommand;
use App\Application\Enrollment\Commands\DeactivateSectionHandler;
use App\Application\Enrollment\Commands\ReactivateClassCommand;
use App\Application\Enrollment\Commands\ReactivateClassHandler;
use App\Application\Enrollment\Commands\ReactivateSectionCommand;
use App\Application\Enrollment\Commands\ReactivateSectionHandler;
use App\Application\Enrollment\DTOs\ClassDTO;
use App\Application\Enrollment\DTOs\SectionDTO;
use App\Application\Enrollment\Queries\GetClassHandler;
use App\Application\Enrollment\Queries\GetClassQuery;
use App\Application\Enrollment\Queries\GetSectionHandler;
use App\Application\Enrollment\Queries\GetSectionQuery;
use App\Application\Enrollment\Queries\ListClassesHandler;
use App\Application\Enrollment\Queries\ListClassesQuery;
use App\Application\Enrollment\Queries\ListClassSectionsHandler;
use App\Application\Enrollment\Queries\ListClassSectionsQuery;
use App\Domain\Enrollment\ValueObjects\EnrollmentStructureStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Enrollment\DeactivateClassRequest;
use App\Http\Requests\Enrollment\DeactivateSectionRequest;
use App\Http\Requests\Enrollment\ListClassesRequest;
use App\Http\Requests\Enrollment\ListClassSectionsRequest;
use App\Http\Requests\Enrollment\ReactivateClassRequest;
use App\Http\Requests\Enrollment\ReactivateSectionRequest;
use App\Http\Requests\Enrollment\ShowClassRequest;
use App\Http\Requests\Enrollment\ShowSectionRequest;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\JsonResponse;

class EnrollmentStructureController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function indexClasses(
        ListClassesRequest $request,
        ListClassesHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $academicYearId = $request->filled('academic_year_id')
            ? (int) $request->query('academic_year_id')
            : null;

        $items = $handler->handle(new ListClassesQuery($schoolId, $academicYearId));

        $this->securityAudit->record(
            SecurityEventType::EnrollmentDataAccess,
            'enrollment.classes.index',
            'viewed',
            $request->user(),
            'classes',
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(fn (ClassDTO $dto): array => $this->classPayload($dto), $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function showClass(
        ShowClassRequest $request,
        int $class,
        GetClassHandler $handler,
    ): JsonResponse {
        $dto = $handler->handle(new GetClassQuery(
            schoolId: $this->schoolContext->requireId(),
            classId: $class,
        ));

        if ($dto === null) {
            return response()->json([
                'message' => 'Enrollment class not found.',
                'error_code' => 'enrollment.class_not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $this->securityAudit->record(
            SecurityEventType::EnrollmentDataAccess,
            'enrollment.classes.show',
            'viewed',
            $request->user(),
            'class:'.$class,
            [],
        );

        return response()->json([
            'data' => $this->classPayload($dto),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function indexSections(
        ListClassSectionsRequest $request,
        int $class,
        ListClassSectionsHandler $handler,
    ): JsonResponse {
        $items = $handler->handle(new ListClassSectionsQuery(
            schoolId: $this->schoolContext->requireId(),
            classId: $class,
        ));

        if ($items === null) {
            return response()->json([
                'message' => 'Enrollment class not found.',
                'error_code' => 'enrollment.class_not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $this->securityAudit->record(
            SecurityEventType::EnrollmentDataAccess,
            'enrollment.classes.sections.index',
            'viewed',
            $request->user(),
            'class:'.$class,
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(fn (SectionDTO $dto): array => $this->sectionPayload($dto), $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function showSection(
        ShowSectionRequest $request,
        int $section,
        GetSectionHandler $handler,
    ): JsonResponse {
        $dto = $handler->handle(new GetSectionQuery(
            schoolId: $this->schoolContext->requireId(),
            sectionId: $section,
        ));

        if ($dto === null) {
            return response()->json([
                'message' => 'Enrollment section not found.',
                'error_code' => 'enrollment.section_not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $this->securityAudit->record(
            SecurityEventType::EnrollmentDataAccess,
            'enrollment.sections.show',
            'viewed',
            $request->user(),
            'section:'.$section,
            [],
        );

        return response()->json([
            'data' => $this->sectionPayload($dto),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function deactivateClass(
        DeactivateClassRequest $request,
        int $class,
        DeactivateClassHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new DeactivateClassCommand(
            schoolId: $this->schoolContext->requireId(),
            classId: $class,
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            correlationId: CorrelationContext::id(),
        ));

        $this->securityAudit->record(
            SecurityEventType::EnrollmentDataModified,
            'enrollment.classes.deactivate',
            'deactivated',
            $request->user(),
            'class:'.$result->classId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'id' => $result->classId,
                'status' => EnrollmentStructureStatus::Inactive->value,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function reactivateClass(
        ReactivateClassRequest $request,
        int $class,
        ReactivateClassHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new ReactivateClassCommand(
            schoolId: $this->schoolContext->requireId(),
            classId: $class,
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            correlationId: CorrelationContext::id(),
        ));

        $this->securityAudit->record(
            SecurityEventType::EnrollmentDataModified,
            'enrollment.classes.reactivate',
            'reactivated',
            $request->user(),
            'class:'.$result->classId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'id' => $result->classId,
                'status' => EnrollmentStructureStatus::Active->value,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function deactivateSection(
        DeactivateSectionRequest $request,
        int $section,
        DeactivateSectionHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new DeactivateSectionCommand(
            schoolId: $this->schoolContext->requireId(),
            sectionId: $section,
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            correlationId: CorrelationContext::id(),
        ));

        $this->securityAudit->record(
            SecurityEventType::EnrollmentDataModified,
            'enrollment.sections.deactivate',
            'deactivated',
            $request->user(),
            'section:'.$result->sectionId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'id' => $result->sectionId,
                'status' => EnrollmentStructureStatus::Inactive->value,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function reactivateSection(
        ReactivateSectionRequest $request,
        int $section,
        ReactivateSectionHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new ReactivateSectionCommand(
            schoolId: $this->schoolContext->requireId(),
            sectionId: $section,
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            correlationId: CorrelationContext::id(),
        ));

        $this->securityAudit->record(
            SecurityEventType::EnrollmentDataModified,
            'enrollment.sections.reactivate',
            'reactivated',
            $request->user(),
            'section:'.$result->sectionId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'id' => $result->sectionId,
                'status' => EnrollmentStructureStatus::Active->value,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    /** @return array<string, mixed> */
    private function classPayload(ClassDTO $dto): array
    {
        return [
            'id' => $dto->id,
            'school_id' => $dto->schoolId,
            'academic_year_id' => $dto->academicYearId,
            'grade_level_id' => $dto->gradeLevelId,
            'code' => $dto->code,
            'name' => $dto->name,
            'capacity' => $dto->capacity,
            'status' => $dto->status,
            'created_at' => $dto->createdAt,
            'updated_at' => $dto->updatedAt,
        ];
    }

    /** @return array<string, mixed> */
    private function sectionPayload(SectionDTO $dto): array
    {
        return [
            'id' => $dto->id,
            'class_id' => $dto->classId,
            'school_id' => $dto->schoolId,
            'code' => $dto->code,
            'name' => $dto->name,
            'capacity' => $dto->capacity,
            'homeroom_teacher_id' => $dto->homeroomTeacherId,
            'status' => $dto->status,
            'created_at' => $dto->createdAt,
            'updated_at' => $dto->updatedAt,
        ];
    }
}
