<?php

namespace App\Http\Controllers\Api;

use App\Application\Portal\Commands\LinkPortalPartyScopeCommand;
use App\Application\Portal\Commands\LinkPortalPartyScopeHandler;
use App\Application\Portal\Commands\UnlinkPortalPartyScopeCommand;
use App\Application\Portal\Commands\UnlinkPortalPartyScopeHandler;
use App\Application\Portal\DTOs\PortalScopeDTO;
use App\Application\Portal\Queries\GetPortalScopeHandler;
use App\Application\Portal\Queries\GetPortalScopeQuery;
use App\Application\Portal\Queries\ListPortalPartyScopesHandler;
use App\Application\Portal\Queries\ListPortalPartyScopesQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\LinkPortalPartyScopeRequest;
use App\Http\Requests\Portal\ListPortalPartyScopesRequest;
use App\Http\Requests\Portal\ShowPortalScopeRequest;
use App\Http\Requests\Portal\UnlinkPortalPartyScopeRequest;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\JsonResponse;

class PortalScopesController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function store(
        LinkPortalPartyScopeRequest $request,
        LinkPortalPartyScopeHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new LinkPortalPartyScopeCommand(
            schoolId: $schoolId,
            userId: (int) $request->validated('user_id'),
            scopeType: (string) $request->validated('scope_type'),
            scopeId: (int) $request->validated('scope_id'),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            return response()->json([
                'message' => 'Portal scope link rejected.',
                'error_code' => $result->errors[0] ?? 'portal.scopes.link_failed',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 422);
        }

        $this->securityAudit->record(
            SecurityEventType::PrivilegeChanged,
            'portal.scopes.link',
            'linked',
            $request->user(),
            'user:'.$request->validated('user_id'),
            [
                'scope_type' => $request->validated('scope_type'),
                'scope_id' => (int) $request->validated('scope_id'),
                'scope_row_id' => $result->scopeRowId,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
        );

        return response()->json([
            'data' => [
                'scope_row_id' => $result->scopeRowId,
                'user_id' => (int) $request->validated('user_id'),
                'scope_type' => $request->validated('scope_type'),
                'scope_id' => (int) $request->validated('scope_id'),
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function destroy(
        UnlinkPortalPartyScopeRequest $request,
        UnlinkPortalPartyScopeHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new UnlinkPortalPartyScopeCommand(
            schoolId: $schoolId,
            userId: (int) $request->validated('user_id'),
            scopeType: (string) $request->validated('scope_type'),
            scopeId: (int) $request->validated('scope_id'),
        ));

        if ($result->failed()) {
            return response()->json([
                'message' => 'Portal scope unlink rejected.',
                'error_code' => $result->errors[0] ?? 'portal.scopes.unlink_failed',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 422);
        }

        $this->securityAudit->record(
            SecurityEventType::PrivilegeChanged,
            'portal.scopes.unlink',
            $result->wasPresent ? 'unlinked' : 'already_absent',
            $request->user(),
            'user:'.$request->validated('user_id'),
            [
                'scope_type' => $request->validated('scope_type'),
                'scope_id' => (int) $request->validated('scope_id'),
                'was_present' => $result->wasPresent,
            ],
        );

        return response()->json([
            'data' => [
                'user_id' => (int) $request->validated('user_id'),
                'scope_type' => $request->validated('scope_type'),
                'scope_id' => (int) $request->validated('scope_id'),
                'was_present' => $result->wasPresent,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function index(
        ListPortalPartyScopesRequest $request,
        ListPortalPartyScopesHandler $handler,
    ): JsonResponse {
        $dtoList = $handler->handle(new ListPortalPartyScopesQuery(
            userId: (int) $request->validated('user_id'),
        ));

        if ($dtoList === null) {
            return response()->json([
                'message' => 'User not found.',
                'error_code' => 'portal.scopes.user_not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $this->securityAudit->record(
            SecurityEventType::PrivilegeChanged,
            'portal.scopes.list',
            'viewed',
            $request->user(),
            'user:'.$request->validated('user_id'),
            ['count' => count($dtoList)],
        );

        return response()->json([
            'data' => array_map(
                static fn (PortalScopeDTO $d): array => [
                    'id' => $d->id,
                    'user_id' => $d->userId,
                    'scope_type' => $d->scopeType,
                    'scope_id' => $d->scopeId,
                    'created_at' => $d->createdAt,
                ],
                $dtoList,
            ),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function show(
        ShowPortalScopeRequest $request,
        int $scope,
        GetPortalScopeHandler $handler,
    ): JsonResponse {
        $dto = $handler->handle(new GetPortalScopeQuery($scope));

        if ($dto === null) {
            return response()->json([
                'message' => 'Portal scope not found.',
                'error_code' => 'portal.scopes.not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $this->securityAudit->record(
            SecurityEventType::PrivilegeChanged,
            'portal.scopes.show',
            'viewed',
            $request->user(),
            'scope:'.$scope,
            [],
        );

        return response()->json([
            'data' => [
                'id' => $dto->id,
                'user_id' => $dto->userId,
                'scope_type' => $dto->scopeType,
                'scope_id' => $dto->scopeId,
                'created_at' => $dto->createdAt,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }
}
