<?php

namespace App\Http\Controllers\Api;

use App\Application\Workflow\Commands\CreateApprovalFlowCommand;
use App\Application\Workflow\Commands\CreateApprovalFlowHandler;
use App\Application\Workflow\Commands\DeactivateApprovalFlowCommand;
use App\Application\Workflow\Commands\DeactivateApprovalFlowHandler;
use App\Application\Workflow\Commands\ReactivateApprovalFlowCommand;
use App\Application\Workflow\Commands\ReactivateApprovalFlowHandler;
use App\Application\Workflow\DTOs\ApprovalFlowDTO;
use App\Application\Workflow\Queries\ListApprovalFlowsHandler;
use App\Application\Workflow\Queries\ListApprovalFlowsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\CreateApprovalFlowRequest;
use App\Http\Requests\Workflow\DeactivateApprovalFlowRequest;
use App\Http\Requests\Workflow\ListApprovalFlowsRequest;
use App\Http\Requests\Workflow\ReactivateApprovalFlowRequest;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\JsonResponse;

class ApprovalFlowController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function store(
        CreateApprovalFlowRequest $request,
        CreateApprovalFlowHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        /** @var list<array{step:int|string, role:string}> $steps */
        $steps = $request->validated('steps');

        $result = $handler->handle(new CreateApprovalFlowCommand(
            schoolId: $schoolId,
            entityType: (string) $request->validated('entity_type'),
            name: (string) $request->validated('name'),
            steps: $steps,
            isActive: $request->boolean('is_active', true),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            return response()->json([
                'message' => 'Approval flow create rejected.',
                'error_code' => $result->errors[0] ?? 'workflow.flow_create_failed',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 422);
        }

        $this->securityAudit->record(
            SecurityEventType::WorkflowDataModified,
            'workflow.approval_flow.create',
            'created',
            $request->user(),
            'flow:'.$result->flowId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'flow_id' => $result->flowId,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function index(
        ListApprovalFlowsRequest $request,
        ListApprovalFlowsHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $entityType = $request->validated('entity_type');
        $activeOnly = $request->has('active_only')
            ? $request->boolean('active_only')
            : null;

        $items = $handler->handle(new ListApprovalFlowsQuery(
            schoolId: $schoolId,
            entityType: $entityType !== null ? (string) $entityType : null,
            activeOnly: $activeOnly === true ? true : null,
        ));

        $this->securityAudit->record(
            SecurityEventType::WorkflowDataAccess,
            'workflow.approval_flow.list',
            'listed',
            $request->user(),
            'school:'.$schoolId,
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(static fn (ApprovalFlowDTO $dto): array => [
                'id' => $dto->id,
                'school_id' => $dto->schoolId,
                'entity_type' => $dto->entityType,
                'name' => $dto->name,
                'steps' => $dto->steps,
                'is_active' => $dto->isActive,
                'created_at' => $dto->createdAt,
            ], $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function deactivate(
        int $approvalFlow,
        DeactivateApprovalFlowRequest $request,
        DeactivateApprovalFlowHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new DeactivateApprovalFlowCommand(
            schoolId: $this->schoolContext->requireId(),
            flowId: $approvalFlow,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'workflow.flow_deactivate_failed';

            return response()->json([
                'message' => 'Approval flow deactivate rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'workflow.flow_not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::WorkflowDataModified,
            'workflow.approval_flow.deactivate',
            'deactivated',
            $request->user(),
            'flow:'.$approvalFlow,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'flow_id' => $result->flowId,
                'is_active' => false,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function reactivate(
        int $approvalFlow,
        ReactivateApprovalFlowRequest $request,
        ReactivateApprovalFlowHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new ReactivateApprovalFlowCommand(
            schoolId: $this->schoolContext->requireId(),
            flowId: $approvalFlow,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'workflow.flow_reactivate_failed';

            return response()->json([
                'message' => 'Approval flow reactivate rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'workflow.flow_not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::WorkflowDataModified,
            'workflow.approval_flow.reactivate',
            'reactivated',
            $request->user(),
            'flow:'.$approvalFlow,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'flow_id' => $result->flowId,
                'is_active' => true,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }
}
