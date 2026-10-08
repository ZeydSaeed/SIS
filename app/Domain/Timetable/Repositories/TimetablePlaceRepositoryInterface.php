<?php

namespace App\Domain\Timetable\Repositories;

/**
 * The places lessons can be held in — classrooms (organization.rooms) and workshops (vocational.workshops) —
 * as the «الأماكن» sheet of the timetable manages them. Reads return every status; the engine's own reads
 * (rooms() / workshops()) stay active-only.
 */
interface TimetablePlaceRepositoryInterface
{
    /**
     * @return array{
     *     rooms: list<array{id: int, branch_id: int, branch_name: string, code: string, name: string, capacity: int|null, room_type: int, status: int, used: int}>,
     *     workshops: list<array{id: int, code: string, name: string, capacity: int, safety_capacity: int, room_id: int|null, status: int, used: int}>
     * }
     */
    public function places(int $schoolId): array;

    /** @return array{id: int, branch_id: int, status: int}|null */
    public function findRoom(int $schoolId, int $roomId): ?array;

    /** @return array{id: int, status: int}|null */
    public function findWorkshop(int $schoolId, int $workshopId): ?array;

    public function branchActive(int $schoolId, int $branchId): bool;

    public function roomCodeTaken(int $branchId, string $code, ?int $exceptRoomId = null): bool;

    public function workshopCodeTaken(int $schoolId, string $code, ?int $exceptWorkshopId = null): bool;

    /** An active room of the school (a workshop's room, an activity's room). */
    public function roomActiveInSchool(int $schoolId, int $roomId): bool;

    public function createRoom(int $branchId, string $code, string $name, ?int $capacity, int $roomType, string $at): int;

    public function updateRoom(int $roomId, string $name, ?int $capacity, int $roomType, string $at): void;

    public function setRoomStatus(int $roomId, int $status, string $at): void;

    public function createWorkshop(int $schoolId, string $code, string $name, int $capacity, int $safetyCapacity, ?int $roomId, string $at): int;

    public function updateWorkshop(int $workshopId, string $name, int $capacity, int $safetyCapacity, ?int $roomId, string $at): void;

    public function setWorkshopStatus(int $workshopId, int $status, string $at): void;

    /** Active lessons, active activities and active workshops that point at the room. */
    public function roomUsage(int $schoolId, int $roomId): int;

    /** Active activities that point at the workshop. */
    public function workshopUsage(int $schoolId, int $workshopId): int;
}
