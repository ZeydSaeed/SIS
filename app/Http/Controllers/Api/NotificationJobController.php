<?php

namespace App\Http\Controllers\Api;

use App\Application\Communication\Commands\CancelNotificationJobCommand;
use App\Application\Communication\Commands\CancelNotificationJobHandler;
use App\Application\Communication\Commands\CompleteNotificationJobCommand;
use App\Application\Communication\Commands\CompleteNotificationJobHandler;
use App\Application\Communication\Commands\CreateNotificationJobCommand;
use App\Application\Communication\Commands\CreateNotificationJobHandler;
use App\Application\Communication\DTOs\NotificationJobDTO;
use App\Application\Communication\Queries\GetNotificationJobHandler;
use App\Application\Communication\Queries\GetNotificationJobQuery;
use App\Application\Communication\Queries\ListNotificationJobsHandler;
use App\Application\Communication\Queries\ListNotificationJobsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\CancelNotificationJobRequest;
use App\Http\Requests\Communication\CompleteNotificationJobRequest;
use App\Http\Requests\Communication\CreateNotificationJobRequest;
use App\Http\Requests\Communication\ListNotificationJobsRequest;
use App\Http\Requests\Communication\ShowNotificationJobRequest;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\JsonResponse;

class NotificationJobController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function store(
        CreateNotificationJobRequest $request,
        CreateNotificationJobHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new CreateNotificationJobCommand(
            schoolId: $schoolId,
            templateId: (int) $request->validated('template_id'),
            targetFilter: (array) $request->validated('target_filter'),
            totalCount: (int) $request->validated('total_count'),
            createdBy: $request->user()?->id,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            return response()->json([
                'message' => 'Notification job create rejected.',
                'error_code' => $result->errors[0] ?? 'communication.job_create_failed',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 422);
        }

        $this->securityAudit->record(
            SecurityEventType::CommunicationDataModified,
            'communication.jobs.create',
            'created',
            $request->user(),
            'notification_job:'.$result->notificationJobId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'notification_job_id' => $result->notificationJobId,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function show(
        int $job,
        ShowNotificationJobRequest $request,
        GetNotificationJobHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $dto = $handler->handle(new GetNotificationJobQuery(
            schoolId: $schoolId,
            jobId: $job,
        ));

        if ($dto === null) {
            return response()->json([
                'message' => 'Notification job not found.',
                'error_code' => 'communication.job_not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $this->securityAudit->record(
            SecurityEventType::CommunicationDataAccess,
            'communication.jobs.show',
            'viewed',
            $request->user(),
            'notification_job:'.$job,
            [],
        );

        return response()->json([
            'data' => [
                'id' => $dto->id,
                'school_id' => $dto->schoolId,
                'job_id' => $dto->jobId,
                'template_id' => $dto->templateId,
                'target_filter' => $dto->targetFilter,
                'total_count' => $dto->totalCount,
                'sent_count' => $dto->sentCount,
                'status' => $dto->status,
                'created_by' => $dto->createdBy,
                'created_at' => $dto->createdAt,
                'completed_at' => $dto->completedAt,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function index(
        ListNotificationJobsRequest $request,
        ListNotificationJobsHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $items = $handler->handle(new ListNotificationJobsQuery(
            schoolId: $schoolId,
            status: $request->validated('job_status') !== null
                ? (int) $request->validated('job_status')
                : null,
            limit: (int) ($request->validated('limit') ?? 50),
        ));

        $this->securityAudit->record(
            SecurityEventType::CommunicationDataAccess,
            'communication.jobs.index',
            'listed',
            $request->user(),
            'school:'.$schoolId,
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(static fn (NotificationJobDTO $dto): array => [
                'id' => $dto->id,
                'school_id' => $dto->schoolId,
                'job_id' => $dto->jobId,
                'template_id' => $dto->templateId,
                'target_filter' => $dto->targetFilter,
                'total_count' => $dto->totalCount,
                'sent_count' => $dto->sentCount,
                'status' => $dto->status,
                'created_by' => $dto->createdBy,
                'created_at' => $dto->createdAt,
                'completed_at' => $dto->completedAt,
            ], $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function complete(
        int $job,
        CompleteNotificationJobRequest $request,
        CompleteNotificationJobHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new CompleteNotificationJobCommand(
            schoolId: $this->schoolContext->requireId(),
            notificationJobId: $job,
            sentCount: (int) $request->validated('sent_count'),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'communication.job_complete_failed';

            return response()->json([
                'message' => 'Notification job complete rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'communication.job_not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::CommunicationDataModified,
            'communication.jobs.complete',
            'completed',
            $request->user(),
            'notification_job:'.$result->notificationJobId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'notification_job_id' => $result->notificationJobId,
                'status' => 2,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function cancel(
        int $job,
        CancelNotificationJobRequest $request,
        CancelNotificationJobHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new CancelNotificationJobCommand(
            schoolId: $this->schoolContext->requireId(),
            notificationJobId: $job,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'communication.job_cancel_failed';

            return response()->json([
                'message' => 'Notification job cancel rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'communication.job_not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::CommunicationDataModified,
            'communication.jobs.cancel',
            'cancelled',
            $request->user(),
            'notification_job:'.$result->notificationJobId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'notification_job_id' => $result->notificationJobId,
                'status' => 3,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }
}
