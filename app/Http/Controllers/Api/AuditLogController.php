<?php

namespace App\Http\Controllers\Api;

use App\Application\Audit\Commands\RegisterAuditLogCommand;
use App\Application\Audit\Commands\RegisterAuditLogHandler;
use App\Application\Audit\DTOs\AuditLogDTO;
use App\Application\Audit\Queries\ListAuditLogsHandler;
use App\Application\Audit\Queries\ListAuditLogsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Audit\ListAuditLogsRequest;
use App\Http\Requests\Audit\RegisterAuditLogRequest;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\JsonResponse;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function store(
        RegisterAuditLogRequest $request,
        RegisterAuditLogHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new RegisterAuditLogCommand(
            schoolId: $schoolId,
            action: (string) $request->validated('action'),
            entityType: (string) $request->validated('entity_type'),
            entityId: $request->validated('entity_id') !== null
                ? (int) $request->validated('entity_id')
                : null,
            oldValues: $request->validated('old_values'),
            newValues: $request->validated('new_values'),
            userId: $request->user()?->id,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            correlationId: CorrelationContext::id(),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            return response()->json([
                'message' => 'Audit log register rejected.',
                'error_code' => $result->errors[0] ?? 'audit.register_failed',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 422);
        }

        $this->securityAudit->record(
            SecurityEventType::AuditTrailDataModified,
            'audit.logs.register',
            'registered',
            $request->user(),
            'audit_log:'.$result->auditLogId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'audit_log_id' => $result->auditLogId,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function index(
        ListAuditLogsRequest $request,
        ListAuditLogsHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $items = $handler->handle(new ListAuditLogsQuery(
            schoolId: $schoolId,
            entityType: $request->validated('entity_type'),
            entityId: $request->validated('entity_id') !== null
                ? (int) $request->validated('entity_id')
                : null,
            limit: (int) ($request->validated('limit') ?? 50),
        ));

        $this->securityAudit->record(
            SecurityEventType::AuditTrailDataAccess,
            'audit.logs.index',
            'listed',
            $request->user(),
            'school:'.$schoolId,
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(static fn (AuditLogDTO $dto): array => [
                'id' => $dto->id,
                'school_id' => $dto->schoolId,
                'user_id' => $dto->userId,
                'action' => $dto->action,
                'entity_type' => $dto->entityType,
                'entity_id' => $dto->entityId,
                'old_values' => $dto->oldValues,
                'new_values' => $dto->newValues,
                'ip_address' => $dto->ipAddress,
                'user_agent' => $dto->userAgent,
                'correlation_id' => $dto->correlationId,
                'created_at' => $dto->createdAt,
            ], $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }
}
