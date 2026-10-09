<?php

namespace App\Domain\Timetable\Services;

use App\Domain\Organization\Services\RoomCatalogueGuard;
use App\Domain\Timetable\Repositories\TimetablePlaceRepositoryInterface;

/**
 * Preconditions of saving a room or a workshop and of taking one out of service. Answers with the first broken
 * rule's error code (Arabic text in the UI) — the database constraints behind it never surface as a 500.
 */
final class TimetablePlaceGuard
{
    public const CLASSROOM = 1;

    public const PRACTICAL = 2;

    public const MAX_CAPACITY = 500;

    public function __construct(
        private readonly TimetablePlaceRepositoryInterface $places,
    ) {}

    /** @return string|null error code */
    public function roomRejection(int $schoolId, ?int $roomId, int $branchId, string $code, string $name, ?int $capacity, int $roomType): ?string
    {
        $code = trim($code);
        if ($roomId === null && ($code === '' || mb_strlen($code) > 30)) {
            return 'timetable.place_code_invalid';
        }
        if (trim($name) === '' || mb_strlen(trim($name)) > RoomCatalogueGuard::MAX_NAME) {
            return 'timetable.place_name_invalid';
        }
        if ($capacity !== null && ($capacity < 1 || $capacity > self::MAX_CAPACITY)) {
            return 'timetable.place_capacity_invalid';
        }
        if (! in_array($roomType, [self::CLASSROOM, self::PRACTICAL], true)) {
            return 'timetable.room_type_invalid';
        }
        if ($roomId !== null) {
            return $this->places->findRoom($schoolId, $roomId) === null ? 'timetable.room_not_found' : null;
        }
        if (! $this->places->branchActive($schoolId, $branchId)) {
            return 'timetable.place_branch_invalid';
        }

        return $this->places->roomCodeTaken($branchId, strtoupper($code)) ? 'timetable.place_code_taken' : null;
    }

    /** @return string|null error code */
    public function workshopRejection(int $schoolId, ?int $workshopId, string $code, string $name, int $capacity, int $safetyCapacity, ?int $roomId): ?string
    {
        $code = trim($code);
        if ($workshopId === null && ($code === '' || mb_strlen($code) > 30)) {
            return 'timetable.place_code_invalid';
        }
        if (trim($name) === '' || mb_strlen(trim($name)) > RoomCatalogueGuard::MAX_NAME) {
            return 'timetable.place_name_invalid';
        }
        if ($capacity < 1 || $capacity > self::MAX_CAPACITY) {
            return 'timetable.place_capacity_invalid';
        }
        if ($safetyCapacity < 1 || $safetyCapacity > $capacity) {
            return 'timetable.workshop_safety_invalid';
        }
        if ($roomId !== null && ! $this->places->roomActiveInSchool($schoolId, $roomId)) {
            return 'timetable.workshop_room_invalid';
        }
        if ($workshopId !== null) {
            return $this->places->findWorkshop($schoolId, $workshopId) === null ? 'timetable.workshop_not_found' : null;
        }

        return $this->places->workshopCodeTaken($schoolId, strtoupper($code)) ? 'timetable.place_code_taken' : null;
    }
}
