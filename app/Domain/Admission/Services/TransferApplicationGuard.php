<?php

namespace App\Domain\Admission\Services;

use App\Domain\Admission\Repositories\AdmissionRepositoryInterface;
use App\Domain\Admission\ValueObjects\ApplicationPeriodStatus;
use App\Domain\Admission\ValueObjects\ApplicationStatus;

/**
 * Transfers page — moving an admission application to another school,
 * request kind (قبول مهني / تحويل أكاديمي) or academic year (period of that year).
 */
final class TransferApplicationGuard
{
    public function __construct(
        private readonly AdmissionRepositoryInterface $admission,
    ) {}

    /**
     * @param  list<int>  $allowedSchoolIds  Active schools the user is linked to.
     * @return array{code: string|null, application: array<string, mixed>|null, period: array<string, mixed>|null}
     */
    public function check(
        int $applicationId,
        int $fromSchoolId,
        int $toSchoolId,
        int $toRequestKind,
        int $toPeriodId,
        array $allowedSchoolIds,
    ): array {
        $application = $this->admission->findApplicationForSchool($applicationId, $fromSchoolId);
        if ($application === null) {
            return ['code' => 'admission.transfer_application_not_found', 'application' => null, 'period' => null];
        }
        if ($application['student_id'] !== null || $application['status'] === ApplicationStatus::Converted->value) {
            return ['code' => 'admission.transfer_application_converted', 'application' => $application, 'period' => null];
        }
        if (! in_array($toRequestKind, [1, 2], true)) {
            return ['code' => 'admission.transfer_request_kind_invalid', 'application' => $application, 'period' => null];
        }
        if (! in_array($toSchoolId, $allowedSchoolIds, true)) {
            return ['code' => 'admission.transfer_school_not_allowed', 'application' => $application, 'period' => null];
        }

        $period = $this->admission->findPeriod($toPeriodId);
        if ($period === null || $period['status'] !== ApplicationPeriodStatus::Active->value) {
            return ['code' => 'admission.transfer_period_invalid', 'application' => $application, 'period' => null];
        }
        if (
            ($period['directorate_id'] ?? null) !== null
            && $this->admission->schoolDirectorateId($toSchoolId) !== $period['directorate_id']
        ) {
            return ['code' => 'admission.period_directorate_mismatch', 'application' => $application, 'period' => null];
        }

        $unchanged = $toSchoolId === $fromSchoolId
            && $toRequestKind === $application['request_kind']
            && $toPeriodId === $application['application_period_id'];
        if ($unchanged) {
            return ['code' => 'admission.transfer_nothing_changed', 'application' => $application, 'period' => $period];
        }

        return ['code' => null, 'application' => $application, 'period' => $period];
    }
}
