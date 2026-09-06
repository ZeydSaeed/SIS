<?php

namespace App\Domain\Enrollment\Specifications;

use App\Domain\Shared\AbstractSpecification;
use App\Domain\Student\Entities\Student;

final class EligibleForEnrollmentSpecification extends AbstractSpecification
{
    public function isSatisfiedBy(object $candidate): bool
    {
        return $this->unsatisfiedReasons($candidate) === [];
    }

    /**
     * @return list<string>
     */
    public function unsatisfiedReasons(object $candidate): array
    {
        if (! $candidate instanceof Student) {
            return ['Invalid student context for enrollment'];
        }

        if (! $candidate->canEnroll()) {
            return ['Student is not active or eligible for enrollment'];
        }

        return [];
    }
}
