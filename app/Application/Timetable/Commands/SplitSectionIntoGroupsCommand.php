<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;

/**
 * «تقسيم الشعبة»: a new division of the section into groups — by a group count, or so no group exceeds a
 * capacity (a workshop's safety capacity). Students are dealt into the groups in name order.
 */
final readonly class SplitSectionIntoGroupsCommand implements Command
{
    /** @param  list<string>  $groupNames  optional names (default: 1, 2, 3 …) */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $sectionId,
        public string $name,
        public ?int $groupCount,
        public ?int $capacity,
        public array $groupNames,
        public ?int $userId,
        public ?string $idempotencyKey = null,
    ) {}
}
