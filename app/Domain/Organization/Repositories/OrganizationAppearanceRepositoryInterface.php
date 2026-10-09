<?php

namespace App\Domain\Organization\Repositories;

use App\Domain\Shared\ValueObjects\DisplayAppearance;

/**
 * Abbreviation and colour of the organization entities the timetable shows (branch, department, room, room type).
 * Writes only rows of the given school; answers false when the row is not the school's.
 */
interface OrganizationAppearanceRepositoryInterface
{
    public const TARGETS = ['branch', 'department', 'room', 'room_type'];

    public function belongsToSchool(string $target, int $schoolId, int $id): bool;

    public function setAppearance(string $target, int $id, DisplayAppearance $appearance, string $at): void;
}
