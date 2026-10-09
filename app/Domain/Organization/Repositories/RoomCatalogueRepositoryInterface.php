<?php

namespace App\Domain\Organization\Repositories;

use App\Domain\Organization\Data\RoomDetails;
use App\Domain\Shared\ValueObjects\DisplayAppearance;

/**
 * «الغرف الدراسية»: the school's rooms (organization.rooms, owned through the branch) and the managed room types
 * (organization.room_types: system defaults with school_id NULL + the school's own). Every read and write is
 * scoped to the school — a room is the school's when its branch is.
 */
interface RoomCatalogueRepositoryInterface
{
    public const ACTIVE = 1;

    public const INACTIVE = 2;

    /**
     * @param  array{search?: string|null, branch_id?: int|null, room_type_id?: int|null, status?: int|null, practical?: bool|null}  $filters
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function page(int $schoolId, array $filters, string $sort, string $direction, int $page, int $perPage): array;

    /** @return array{total: int, active: int, practical: int, capacity: int, by_kind: array<int, int>} */
    public function stats(int $schoolId): array;

    /** @return list<array{id: int, school_id: int|null, code: string, name: string, abbreviation: string|null, kind: int, supports_practical: bool, color_hue: int|null, status: int, rooms: int}> */
    public function types(int $schoolId): array;

    /** @return array{id: int, branch_id: int, status: int}|null */
    public function findRoom(int $schoolId, int $roomId): ?array;

    /** @return array{id: int, school_id: int|null, kind: int, status: int}|null a type the school may use (its own or a system one) */
    public function findType(int $schoolId, int $typeId): ?array;

    public function branchActive(int $schoolId, int $branchId): bool;

    public function departmentOfBranch(int $branchId, int $departmentId): bool;

    public function roomCodeTaken(int $branchId, string $code): bool;

    public function typeCodeTaken(int $schoolId, string $code, ?int $exceptTypeId = null): bool;

    /** Lessons, activities and workshops still pointing at the room. */
    public function roomUsage(int $roomId): int;

    public function typeUsage(int $schoolId, int $typeId): int;

    public function createRoom(int $branchId, string $code, RoomDetails $details, string $at): int;

    public function updateRoom(int $roomId, RoomDetails $details, string $at): void;

    public function setRoomStatus(int $roomId, int $status, string $at): void;

    public function createType(int $schoolId, string $code, string $name, DisplayAppearance $appearance, int $kind, bool $practical, string $at): int;

    public function updateType(int $typeId, string $name, DisplayAppearance $appearance, int $kind, bool $practical, string $at): void;

    public function setTypeStatus(int $typeId, int $status, string $at): void;
}
