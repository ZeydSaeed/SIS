<?php

namespace App\Application\Graduation\Contracts;

use App\Domain\Graduation\ValueObjects\GraduationAction;

/**
 * Fail-closed authority until HD-31-G institutional permission catalog is locked.
 * Does not invent Permission.php string constants.
 */
interface GraduationAuthorityPort
{
    public function assertSchoolMatches(int $commandSchoolId): void;

    public function assertCan(GraduationAction $action, int $actorUserId, int $schoolId): void;
}
