<?php

namespace App\Domain\Admission\Services;

use App\Domain\Admission\Exceptions\ApplicationPeriodCapacityExceededException;
use App\Domain\Admission\Exceptions\ApplicationPeriodClosedException;
use App\Domain\Admission\Exceptions\ApplicationPeriodNotFoundException;
use App\Domain\Admission\Repositories\AdmissionRepositoryInterface;
use App\Domain\Admission\ValueObjects\ApplicationPeriodStatus;
use DomainException;

final class CreateApplicationDraftGuard
{
    public function __construct(
        private readonly AdmissionRepositoryInterface $admission,
    ) {}

    /**
     * @return array{
     *     id:int,
     *     school_id:int,
     *     academic_year_id:int,
     *     name:string,
     *     status:int,
     *     start_date:string,
     *     end_date:string,
     *     max_applications:?int
     * }
     */
    public function assertOpenPeriod(int $periodId, int $schoolId, int $gender): array
    {
        if (! in_array($gender, [1, 2], true)) {
            throw new DomainException('gender must be 1 or 2.');
        }

        $period = $this->admission->findPeriodForSchool($periodId, $schoolId);
        if ($period === null) {
            throw ApplicationPeriodNotFoundException::forId($periodId);
        }

        if ($period['status'] !== ApplicationPeriodStatus::Active->value) {
            throw ApplicationPeriodClosedException::forPeriod($periodId);
        }

        $now = time();
        if ($now < strtotime($period['start_date']) || $now > strtotime($period['end_date'])) {
            throw ApplicationPeriodClosedException::forPeriod($periodId);
        }

        if (
            $period['max_applications'] !== null
            && $this->admission->countApplicationsInPeriod($periodId) >= $period['max_applications']
        ) {
            throw ApplicationPeriodCapacityExceededException::forPeriod($periodId);
        }

        return $period;
    }
}
