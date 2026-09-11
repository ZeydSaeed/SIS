<?php

namespace App\Domain\Graduation\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class CompletionOutcomeNotFoundException extends SisDomainException
{
    public static function forEnrollment(int $schoolId, int $enrollmentId): self
    {
        return new self(
            "Completion outcome was not found for school {$schoolId} enrollment {$enrollmentId}.",
            'graduation.completion_outcome_not_found',
        );
    }
}
