<?php

namespace App\Http\Controllers\Api;

use App\Application\Curriculum\Commands\AddSubjectPrerequisiteCommand;
use App\Application\Curriculum\Commands\AddSubjectPrerequisiteHandler;
use App\Application\Curriculum\Commands\CreateSubjectCommand;
use App\Application\Curriculum\Commands\CreateSubjectHandler;
use App\Application\Curriculum\Commands\DeactivateSubjectCommand;
use App\Application\Curriculum\Commands\DeactivateSubjectHandler;
use App\Application\Curriculum\Commands\DeactivateSubjectPrerequisiteCommand;
use App\Application\Curriculum\Commands\DeactivateSubjectPrerequisiteHandler;
use App\Application\Curriculum\DTOs\PrerequisiteDTO;
use App\Application\Curriculum\DTOs\SubjectDTO;
use App\Application\Curriculum\Queries\ListSubjectPrerequisitesHandler;
use App\Application\Curriculum\Queries\ListSubjectPrerequisitesQuery;
use App\Application\Curriculum\Queries\ListSubjectsHandler;
use App\Application\Curriculum\Queries\ListSubjectsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Curriculum\AddSubjectPrerequisiteRequest;
use App\Http\Requests\Curriculum\CreateSubjectRequest;
use App\Http\Requests\Curriculum\DeactivateSubjectPrerequisiteRequest;
use App\Http\Requests\Curriculum\DeactivateSubjectRequest;
use App\Http\Requests\Curriculum\ListSubjectPrerequisitesRequest;
use App\Http\Requests\Curriculum\ListSubjectsRequest;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use Illuminate\Http\JsonResponse;

class CurriculumController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLoggerInterface $securityAudit,
    ) {}

    public function storeSubject(
        CreateSubjectRequest $request,
        CreateSubjectHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new CreateSubjectCommand(
            code: (string) $request->validated('code'),
            name: (string) $request->validated('name'),
            nameEn: $request->validated('name_en'),
            subjectType: (int) $request->validated('subject_type'),
            creditHours: $request->validated('credit_hours') !== null
                ? (int) $request->validated('credit_hours')
                : null,
            maxGrade: (int) ($request->validated('max_grade') ?? 100),
            passGrade: (int) ($request->validated('pass_grade') ?? 50),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            return response()->json([
                'message' => 'Subject create rejected.',
                'error_code' => $result->errors[0] ?? 'curriculum.subject_create_failed',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 422);
        }

        $this->securityAudit->record(
            SecurityEventType::CurriculumDataModified,
            'curriculum.subjects.store',
            'created',
            $request->user(),
            'subject:'.$result->subjectId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'subject_id' => $result->subjectId,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function indexSubjects(
        ListSubjectsRequest $request,
        ListSubjectsHandler $handler,
    ): JsonResponse {
        $items = $handler->handle(new ListSubjectsQuery);

        $this->securityAudit->record(
            SecurityEventType::CurriculumDataAccess,
            'curriculum.subjects.index',
            'listed',
            $request->user(),
            'subjects',
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(fn (SubjectDTO $dto): array => $this->subjectPayload($dto), $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function deactivateSubject(
        int $subject,
        DeactivateSubjectRequest $request,
        DeactivateSubjectHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new DeactivateSubjectCommand(
            subjectId: $subject,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'curriculum.subject_deactivate_failed';

            return response()->json([
                'message' => 'Subject deactivate rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'curriculum.subject_not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::CurriculumDataModified,
            'curriculum.subjects.deactivate',
            'deactivated',
            $request->user(),
            'subject:'.$result->subjectId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'subject_id' => $result->subjectId,
                'status' => 2,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function storePrerequisite(
        int $subject,
        AddSubjectPrerequisiteRequest $request,
        AddSubjectPrerequisiteHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new AddSubjectPrerequisiteCommand(
            subjectId: $subject,
            prerequisiteSubjectId: (int) $request->validated('prerequisite_subject_id'),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'curriculum.prerequisite_add_failed';

            return response()->json([
                'message' => 'Subject prerequisite add rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], in_array($code, [
                'curriculum.subject_not_found',
                'curriculum.prerequisite_subject_not_found',
            ], true) ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::CurriculumDataModified,
            'curriculum.prerequisites.store',
            'added',
            $request->user(),
            'prerequisite:'.$result->prerequisiteId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'prerequisite_id' => $result->prerequisiteId,
                'subject_id' => $subject,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function indexPrerequisites(
        int $subject,
        ListSubjectPrerequisitesRequest $request,
        ListSubjectPrerequisitesHandler $handler,
    ): JsonResponse {
        $items = $handler->handle(new ListSubjectPrerequisitesQuery($subject));

        $this->securityAudit->record(
            SecurityEventType::CurriculumDataAccess,
            'curriculum.prerequisites.index',
            'listed',
            $request->user(),
            'subject:'.$subject,
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(fn (PrerequisiteDTO $dto): array => $this->prerequisitePayload($dto), $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function deactivatePrerequisite(
        int $prerequisite,
        DeactivateSubjectPrerequisiteRequest $request,
        DeactivateSubjectPrerequisiteHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new DeactivateSubjectPrerequisiteCommand(
            prerequisiteId: $prerequisite,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'curriculum.prerequisite_deactivate_failed';

            return response()->json([
                'message' => 'Subject prerequisite deactivate rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'curriculum.prerequisite_not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::CurriculumDataModified,
            'curriculum.prerequisites.deactivate',
            'deactivated',
            $request->user(),
            'prerequisite:'.$result->prerequisiteId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'prerequisite_id' => $result->prerequisiteId,
                'status' => 2,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function subjectPayload(SubjectDTO $dto): array
    {
        return [
            'id' => $dto->id,
            'code' => $dto->code,
            'name' => $dto->name,
            'name_en' => $dto->nameEn,
            'subject_type' => $dto->subjectType,
            'credit_hours' => $dto->creditHours,
            'max_grade' => $dto->maxGrade,
            'pass_grade' => $dto->passGrade,
            'status' => $dto->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function prerequisitePayload(PrerequisiteDTO $dto): array
    {
        return [
            'id' => $dto->id,
            'subject_id' => $dto->subjectId,
            'prerequisite_subject_id' => $dto->prerequisiteSubjectId,
            'status' => $dto->status,
            'created_at' => $dto->createdAt,
        ];
    }
}
