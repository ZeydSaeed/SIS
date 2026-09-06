<?php

namespace App\Domain\Enrollment\Specifications;

use App\Domain\Enrollment\Data\StudentEnrollmentView;
use App\Domain\Shared\AbstractSpecification;
use App\Domain\Student\Specifications\ActiveStudentSpecification;

final class EligibleForEnrollmentSpecification extends AbstractSpecification
{
    public function __construct(
        private readonly ActiveStudentSpecification $activeStudent = new ActiveStudentSpecification,
    ) {}

    public function isSatisfiedBy(object $candidate): bool
    {
        return $this->unsatisfiedReasons($candidate) === [];
    }

    /**
     * @return list<string>
     */
    public function unsatisfiedReasons(object $candidate): array
    {
        if (! $candidate instanceof StudentEnrollmentView) {
            return ['Invalid student context for enrollment'];
        }

        return $this->activeStudent->unsatisfiedReasons($candidate);
    }
}
