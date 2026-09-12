<?php

namespace App\Application\Workflow\Contracts;

interface ActorSchoolRolesPort
{
    /**
     * Active role codes for the actor within a school (security.roles.code).
     *
     * @return list<string>
     */
    public function roleCodesForUserInSchool(int $userId, int $schoolId): array;
}
