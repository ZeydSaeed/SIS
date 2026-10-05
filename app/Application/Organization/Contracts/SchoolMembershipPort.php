<?php

namespace App\Application\Organization\Contracts;

interface SchoolMembershipPort
{
    /**
     * Copy the user's active role assignments at $fromSchoolId to $toSchoolId.
     *
     * @return int Number of role assignments granted.
     */
    public function grantSameRoles(int $userId, int $fromSchoolId, int $toSchoolId, string $effectiveFrom): int;
}
