<?php

namespace App\Application\Exams\Contracts;

use App\Domain\Exams\ValueObjects\ExamAdministrationAction;

interface ExamAdministrationAuthorityPort
{
    public function assertSchoolMatches(int $commandSchoolId): void;

    public function assertCan(ExamAdministrationAction $action, int $actorUserId, int $schoolId): void;
}
