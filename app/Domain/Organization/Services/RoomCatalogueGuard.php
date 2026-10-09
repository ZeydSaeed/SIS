<?php

namespace App\Domain\Organization\Services;

use App\Domain\Organization\Data\RoomDetails;
use App\Domain\Organization\Repositories\RoomCatalogueRepositoryInterface;
use App\Domain\Organization\ValueObjects\RoomKind;
use App\Domain\Shared\ValueObjects\DisplayAppearance;

/**
 * Rules of «الغرف الدراسية». Answers with the first broken rule's error code (Arabic text in the UI) so a
 * database constraint behind it never surfaces as a 500.
 */
final class RoomCatalogueGuard
{
    public const MAX_NAME = 100;

    public const MAX_CODE = 20;

    public const MAX_CAPACITY = 500;

    public const MAX_TEXT = 2000;

    public function __construct(
        private readonly RoomCatalogueRepositoryInterface $rooms,
    ) {}

    /** @return string|null error code */
    public function roomRejection(int $schoolId, ?int $roomId, ?int $branchId, ?string $code, RoomDetails $details): ?string
    {
        if ($roomId === null) {
            $code = trim((string) $code);
            if ($code === '' || mb_strlen($code) > self::MAX_CODE || preg_match('/^[\p{L}\p{N}._-]+$/u', $code) !== 1) {
                return 'organization.room_code_invalid';
            }
            if ($branchId === null || ! $this->rooms->branchActive($schoolId, $branchId)) {
                return 'organization.room_branch_invalid';
            }
            if ($this->rooms->roomCodeTaken($branchId, mb_strtoupper($code))) {
                return 'organization.room_code_taken';
            }
        } else {
            $room = $this->rooms->findRoom($schoolId, $roomId);
            if ($room === null) {
                return 'organization.room_not_found';
            }
            $branchId = $room['branch_id'];
        }

        $name = trim($details->name);
        if ($name === '' || mb_strlen($name) > self::MAX_NAME) {
            return 'organization.room_name_invalid';
        }
        $appearanceError = $details->appearance->rejection();
        if ($appearanceError !== null) {
            return $appearanceError;
        }
        if ($details->capacity !== null && ($details->capacity < 1 || $details->capacity > self::MAX_CAPACITY)) {
            return 'organization.room_capacity_invalid';
        }
        if ($details->floor !== null && ($details->floor < -5 || $details->floor > 100)) {
            return 'organization.room_floor_invalid';
        }
        foreach ([$details->roomNumber, $details->building, $details->location] as $text) {
            if ($text !== null && mb_strlen(trim($text)) > 150) {
                return 'organization.room_text_too_long';
            }
        }
        foreach ([$details->equipment, $details->suitableFor, $details->notes] as $text) {
            if ($text !== null && mb_strlen($text) > self::MAX_TEXT) {
                return 'organization.room_text_too_long';
            }
        }
        if ($details->roomTypeId !== null) {
            $type = $this->rooms->findType($schoolId, $details->roomTypeId);
            if ($type === null || $type['status'] !== RoomCatalogueRepositoryInterface::ACTIVE) {
                return 'organization.room_type_invalid';
            }
        }
        if ($details->departmentId !== null && ! $this->rooms->departmentOfBranch($branchId, $details->departmentId)) {
            return 'organization.room_department_invalid';
        }

        return null;
    }

    /** @return string|null error code — a room still used by lessons, activities or workshops stays in service */
    public function roomStatusRejection(int $schoolId, int $roomId, bool $active): ?string
    {
        $room = $this->rooms->findRoom($schoolId, $roomId);
        if ($room === null) {
            return 'organization.room_not_found';
        }
        if ($active) {
            return $this->rooms->branchActive($schoolId, $room['branch_id']) ? null : 'organization.room_branch_invalid';
        }

        return $this->rooms->roomUsage($roomId) > 0 ? 'organization.room_in_use' : null;
    }

    /** @return string|null error code */
    public function typeRejection(int $schoolId, ?int $typeId, ?string $code, string $name, DisplayAppearance $appearance, int $kind): ?string
    {
        if ($typeId === null) {
            $code = trim((string) $code);
            if ($code === '' || mb_strlen($code) > 30 || preg_match('/^[\p{L}\p{N}._-]+$/u', $code) !== 1) {
                return 'organization.room_type_code_invalid';
            }
            if ($this->rooms->typeCodeTaken($schoolId, mb_strtoupper($code))) {
                return 'organization.room_type_code_taken';
            }
        } else {
            $type = $this->rooms->findType($schoolId, $typeId);
            if ($type === null) {
                return 'organization.room_type_invalid';
            }
            if ($type['school_id'] === null) {
                // System types are shared by every school: copy one as the school's own type instead.
                return 'organization.room_type_system';
            }
        }
        if (trim($name) === '' || mb_strlen(trim($name)) > self::MAX_NAME) {
            return 'organization.room_type_name_invalid';
        }
        if (RoomKind::tryFrom($kind) === null) {
            return 'organization.room_kind_invalid';
        }

        return $appearance->rejection();
    }

    /** @return string|null error code */
    public function typeStatusRejection(int $schoolId, int $typeId, bool $active): ?string
    {
        $type = $this->rooms->findType($schoolId, $typeId);
        if ($type === null) {
            return 'organization.room_type_invalid';
        }
        if ($type['school_id'] === null) {
            return 'organization.room_type_system';
        }

        return ! $active && $this->rooms->typeUsage($schoolId, $typeId) > 0 ? 'organization.room_type_in_use' : null;
    }
}
