<?php

namespace App\Domain\Attendance\Repositories;

use App\Domain\Attendance\Data\AlertCandidate;

interface AttendanceAlertRepositoryInterface
{
    /**
     * Inserts the candidates that are not already recorded for the same rule · subject · period end.
     *
     * @param  list<AlertCandidate>  $candidates
     * @return int alerts created
     */
    public function record(int $schoolId, int $academicYearId, string $periodStart, string $periodEnd, array $candidates): int;

    /** Open/acknowledged → acknowledged/resolved/dismissed. Returns false when the alert is not in this school. */
    public function setStatus(int $schoolId, int $alertId, int $status, ?int $userId, ?string $note): bool;
}
