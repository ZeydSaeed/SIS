<?php

namespace App\Http\Controllers\Api;

use App\Application\Organization\DTOs\RoomDTO;
use App\Application\Organization\Queries\ListRoomsHandler;
use App\Application\Organization\Queries\ListRoomsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\ListRoomsRequest;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\JsonResponse;

class OrganizationController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function indexRooms(
        ListRoomsRequest $request,
        ListRoomsHandler $handler,
    ): JsonResponse {
        $branchId = $request->validated('branch_id');
        $items = $handler->handle(new ListRoomsQuery(
            schoolId: $this->schoolContext->requireId(),
            branchId: $branchId !== null ? (int) $branchId : null,
        ));

        $this->securityAudit->record(
            SecurityEventType::EnrollmentDataAccess,
            'organization.rooms.index',
            'viewed',
            $request->user(),
            'rooms',
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(static fn (RoomDTO $dto): array => [
                'id' => $dto->id,
                'branch_id' => $dto->branchId,
                'school_id' => $dto->schoolId,
                'code' => $dto->code,
                'name' => $dto->name,
                'capacity' => $dto->capacity,
                'room_type' => $dto->roomType,
                'status' => $dto->status,
                'created_at' => $dto->createdAt,
                'updated_at' => $dto->updatedAt,
            ], $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }
}
