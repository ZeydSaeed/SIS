<?php

namespace App\Application\Student\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class UpdateStudentResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $studentId = null,
        array $errors = [],
    ) {
        parent::__construct($success, $errors);
    }

    public static function success(int $studentId): self
    {
        return new self(true, $studentId);
    }
}
