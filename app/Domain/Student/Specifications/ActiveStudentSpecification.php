<?php

namespace App\Domain\Student\Specifications;

use App\Domain\Shared\AbstractSpecification;

/**
 * Example specification — extend for real enrollment eligibility rules.
 */
final class ActiveStudentSpecification extends AbstractSpecification
{
    public function isSatisfiedBy(object $candidate): bool
    {
        if (! is_object($candidate)) {
            return false;
        }

        if (property_exists($candidate, 'status')) {
            return (int) $candidate->status === 1;
        }

        if (method_exists($candidate, 'getStatus')) {
            return (int) $candidate->getStatus() === 1;
        }

        return false;
    }

    public function unsatisfiedReasons(object $candidate): array
    {
        return $this->isSatisfiedBy($candidate) ? [] : ['Student is not active'];
    }
}
