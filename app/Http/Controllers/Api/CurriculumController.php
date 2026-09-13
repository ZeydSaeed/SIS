<?php

namespace App\Http\Controllers\Api;

use App\Application\Curriculum\Commands\AddSubjectPrerequisiteCommand;
use App\Application\Curriculum\Commands\AddSubjectPrerequisiteHandler;
use App\Application\Curriculum\Commands\DeactivateSubjectPrerequisiteCommand;
use App\Application\Curriculum\Commands\DeactivateSubjectPrerequisiteHandler;
use App\Application\Curriculum\DTOs\PrerequisiteDTO;
use App\Application\Curriculum\Queries\ListSubjectPrerequisitesHandler;
use App\Application\Curriculum\Queries\ListSubjectPrerequisitesQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Curriculum\AddSubjectPrerequisiteRequest;
use App\Http\Requests\Curriculum\DeactivateSubjectPrerequisiteRequest;
use App\Http\Requests\Curriculum\ListSubjectPrerequisitesRequest;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use Illuminate\Http\JsonResponse;

class CurriculumController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLoggerInterface $securityAudit,
    ) {}

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
            'data' => array_map(fn (PrerequisiteDTO $dto): array => $this->payload($dto), $items),
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
    private function payload(PrerequisiteDTO $dto): array
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
