<?php

namespace App\Http\Controllers\Api;

use App\Application\Workflow\Commands\CancelApprovalRequestCommand;
use App\Application\Workflow\Commands\CancelApprovalRequestHandler;
use App\Application\Workflow\Commands\CreateApprovalRequestCommand;
use App\Application\Workflow\Commands\CreateApprovalRequestHandler;
use App\Application\Workflow\Commands\DecideApprovalRequestCommand;
use App\Application\Workflow\Commands\DecideApprovalRequestHandler;
use App\Application\Workflow\DTOs\ApprovalRequestDTO;
use App\Application\Workflow\Queries\GetApprovalRequestHandler;
use App\Application\Workflow\Queries\GetApprovalRequestQuery;
use App\Application\Workflow\Queries\ListApprovalRequestsHandler;
use App\Application\Workflow\Queries\ListApprovalRequestsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\CancelApprovalRequestRequest;
use App\Http\Requests\Workflow\CreateApprovalRequestRequest;
use App\Http\Requests\Workflow\DecideApprovalRequestRequest;
use App\Http\Requests\Workflow\ListApprovalRequestsRequest;
use App\Http\Requests\Workflow\ShowApprovalRequestRequest;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\JsonResponse;

class ApprovalRequestController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function store(
        CreateApprovalRequestRequest $request,
        CreateApprovalRequestHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new CreateApprovalRequestCommand(
            schoolId: $schoolId,
            flowId: (int) $request->validated('flow_id'),
            entityType: (string) $request->validated('entity_type'),
            entityId: (int) $request->validated('entity_id'),
            requestedBy: $request->user()?->id,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            return response()->json([
                'message' => 'Approval request create rejected.',
                'error_code' => $result->errors[0] ?? 'workflow.approval_request_create_failed',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 422);
        }

        $this->securityAudit->record(
            SecurityEventType::WorkflowDataModified,
            'workflow.approval_request.create',
            'created',
            $request->user(),
            'request:'.$result->requestId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'request_id' => $result->requestId,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function index(
        ListApprovalRequestsRequest $request,
        ListApprovalRequestsHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $entityType = $request->validated('entity_type');
        $entityId = $request->validated('entity_id');
        $requestStatus = $request->validated('request_status');

        $items = $handler->handle(new ListApprovalRequestsQuery(
            schoolId: $schoolId,
            entityType: $entityType !== null ? (string) $entityType : null,
            entityId: $entityId !== null ? (int) $entityId : null,
            requestStatus: $requestStatus !== null ? (int) $requestStatus : null,
        ));

        $this->securityAudit->record(
            SecurityEventType::WorkflowDataAccess,
            'workflow.approval_request.list',
            'listed',
            $request->user(),
            'school:'.$schoolId,
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(static fn (ApprovalRequestDTO $dto): array => [
                'id' => $dto->id,
                'school_id' => $dto->schoolId,
                'flow_id' => $dto->flowId,
                'entity_type' => $dto->entityType,
                'entity_id' => $dto->entityId,
                'current_step' => $dto->currentStep,
                'status' => $dto->status,
                'requested_by' => $dto->requestedBy,
                'created_at' => $dto->createdAt,
                'completed_at' => $dto->completedAt,
            ], $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function show(
        int $approvalRequest,
        ShowApprovalRequestRequest $request,
        GetApprovalRequestHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $dto = $handler->handle(new GetApprovalRequestQuery(
            schoolId: $schoolId,
            requestId: $approvalRequest,
        ));

        if ($dto === null) {
            return response()->json([
                'message' => 'Approval request not found.',
                'error_code' => 'workflow.approval_request_not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $this->securityAudit->record(
            SecurityEventType::WorkflowDataAccess,
            'workflow.approval_request.show',
            'viewed',
            $request->user(),
            'request:'.$approvalRequest,
            [],
        );

        return response()->json([
            'data' => [
                'id' => $dto->id,
                'school_id' => $dto->schoolId,
                'flow_id' => $dto->flowId,
                'entity_type' => $dto->entityType,
                'entity_id' => $dto->entityId,
                'current_step' => $dto->currentStep,
                'status' => $dto->status,
                'requested_by' => $dto->requestedBy,
                'created_at' => $dto->createdAt,
                'completed_at' => $dto->completedAt,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function decide(
        int $approvalRequest,
        DecideApprovalRequestRequest $request,
        DecideApprovalRequestHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new DecideApprovalRequestCommand(
            schoolId: $schoolId,
            requestId: $approvalRequest,
            decision: (string) $request->validated('decision'),
            actorUserId: (int) $request->user()->id,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            return response()->json([
                'message' => 'Approval request decide rejected.',
                'error_code' => $result->errors[0] ?? 'workflow.approval_request_decide_failed',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 422);
        }

        $this->securityAudit->record(
            SecurityEventType::WorkflowDataModified,
            'workflow.approval_request.decide',
            (string) $request->validated('decision'),
            $request->user(),
            'request:'.$result->requestId,
            [
                'from_idempotency' => $result->fromIdempotencyCache,
                'status' => $result->status,
                'current_step' => $result->currentStep,
            ],
        );

        return response()->json([
            'data' => [
                'request_id' => $result->requestId,
                'status' => $result->status,
                'current_step' => $result->currentStep,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], 200);
    }

    public function cancel(
        int $approvalRequest,
        CancelApprovalRequestRequest $request,
        CancelApprovalRequestHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new CancelApprovalRequestCommand(
            schoolId: $schoolId,
            requestId: $approvalRequest,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            return response()->json([
                'message' => 'Approval request cancel rejected.',
                'error_code' => $result->errors[0] ?? 'workflow.approval_request_cancel_failed',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 422);
        }

        $this->securityAudit->record(
            SecurityEventType::WorkflowDataModified,
            'workflow.approval_request.cancel',
            'cancel',
            $request->user(),
            'request:'.$result->requestId,
            [
                'from_idempotency' => $result->fromIdempotencyCache,
                'status' => $result->status,
            ],
        );

        return response()->json([
            'data' => [
                'request_id' => $result->requestId,
                'status' => $result->status,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], 200);
    }
}
