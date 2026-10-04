<?php

namespace App\Application\Enrollment\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class ApplyCurriculumToEnrollmentResult extends ApplicationResult
{
    /**
     * @param  list<string>  $errors
     * @param  list<string>  $warnings  "{error_code}:{subject_id}" for each skipped subject
     */
    private function __construct(
        bool $success,
        public int $assignedCount = 0,
        array $errors = [],
        array $warnings = [],
    ) {
        parent::__construct($success, $errors, $warnings);
    }

    /**
     * @param  list<string>  $warnings
     */
    public static function success(int $assignedCount, array $warnings = []): self
    {
        return new self(true, $assignedCount, warnings: $warnings);
    }

    /**
     * @param  list<string>  $errors
     */
    public static function failure(array $errors): self
    {
        return new self(false, errors: $errors);
    }
}
