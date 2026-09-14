<?php

namespace App\Http\Controllers\Api;

use App\Application\Organization\DTOs\BranchDTO;
use App\Application\Organization\DTOs\DepartmentDTO;
use App\Application\Organization\DTOs\RoomDTO;
use App\Application\Organization\Queries\GetRoomHandler;
use App\Application\Organization\Queries\GetRoomQuery;
use App\Application\Organization\Queries\ListBranchesHandler;
use App\Application\Organization\Queries\ListBranchesQuery;
use App\Application\Organization\Queries\ListDepartmentsHandler;
use App\Application\Organization\Queries\ListDepartmentsQuery;
use App\Application\Organization\Queries\ListRoomsHandler;
use App\Application\Organization\Queries\ListRoomsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\ListBranchesRequest;
use App\Http\Requests\Organization\ListDepartmentsRequest;
use App\Http\Requests\Organization\ListRoomsRequest;
use App\Http\Requests\Organization\ShowRoomRequest;
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

    public function indexBranches(
        ListBranchesRequest $request,
        ListBranchesHandler $handler,
    ): JsonResponse {
        $items = $handler->handle(new ListBranchesQuery(
            schoolId: $this->schoolContext->requireId(),
        ));

        $this->securityAudit->record(
            SecurityEventType::EnrollmentDataAccess,
            'organization.branches.index',
            'viewed',
            $request->user(),
            'branches',
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(static fn (BranchDTO $dto): array => [
                'id' => $dto->id,
                'school_id' => $dto->schoolId,
                'code' => $dto->code,
                'name' => $dto->name,
                'address' => $dto->address,
                'status' => $dto->status,
                'created_at' => $dto->createdAt,
                'updated_at' => $dto->updatedAt,
            ], $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function indexDepartments(
        ListDepartmentsRequest $request,
        ListDepartmentsHandler $handler,
    ): JsonResponse {
        $branchId = $request->validated('branch_id');
        $items = $handler->handle(new ListDepartmentsQuery(
            schoolId: $this->schoolContext->requireId(),
            branchId: $branchId !== null ? (int) $branchId : null,
        ));

        $this->securityAudit->record(
            SecurityEventType::EnrollmentDataAccess,
            'organization.departments.index',
            'viewed',
            $request->user(),
            'departments',
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(fn (DepartmentDTO $dto): array => $this->departmentPayload($dto), $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

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
            'data' => array_map(fn (RoomDTO $dto): array => $this->roomPayload($dto), $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function showRoom(
        ShowRoomRequest $request,
        int $room,
        GetRoomHandler $handler,
    ): JsonResponse {
        $dto = $handler->handle(new GetRoomQuery(
            schoolId: $this->schoolContext->requireId(),
            roomId: $room,
        ));

        if ($dto === null) {
            return response()->json([
                'message' => 'Room not found.',
                'error_code' => 'organization.room_not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $this->securityAudit->record(
            SecurityEventType::EnrollmentDataAccess,
            'organization.rooms.show',
            'viewed',
            $request->user(),
            'room:'.$room,
            [],
        );

        return response()->json([
            'data' => $this->roomPayload($dto),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    /** @return array<string, mixed> */
    private function departmentPayload(DepartmentDTO $dto): array
    {
        return [
            'id' => $dto->id,
            'school_id' => $dto->schoolId,
            'branch_id' => $dto->branchId,
            'code' => $dto->code,
            'name' => $dto->name,
            'department_type' => $dto->departmentType,
            'status' => $dto->status,
            'created_at' => $dto->createdAt,
            'updated_at' => $dto->updatedAt,
        ];
    }

    /** @return array<string, mixed> */
    private function roomPayload(RoomDTO $dto): array
    {
        return [
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
        ];
    }
}
