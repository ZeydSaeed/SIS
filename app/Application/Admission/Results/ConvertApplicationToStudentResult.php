<?php

namespace App\Application\Admission\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class ConvertApplicationToStudentResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $applicationId = null,
        public ?int $studentId = null,
        public ?int $academicYearId = null,
        public ?int $branchId = null,
        public ?int $specializationId = null,
        public ?int $gradeLevelId = null,
        public ?string $departmentName = null,
        array $errors = [],
        array $warnings = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    public static function success(
        int $applicationId,
        int $studentId,
        ?int $academicYearId = null,
        ?int $branchId = null,
        ?int $specializationId = null,
        ?int $gradeLevelId = null,
        ?string $departmentName = null,
    ): self {
        return new self(
            true,
            $applicationId,
            $studentId,
            $academicYearId,
            $branchId,
            $specializationId,
            $gradeLevelId,
            $departmentName,
        );
    }

    public static function fromIdempotency(
        int $applicationId,
        int $studentId,
        ?int $academicYearId = null,
        ?int $branchId = null,
        ?int $specializationId = null,
        ?int $gradeLevelId = null,
        ?string $departmentName = null,
    ): self {
        return new self(
            true,
            $applicationId,
            $studentId,
            $academicYearId,
            $branchId,
            $specializationId,
            $gradeLevelId,
            $departmentName,
            fromIdempotencyCache: true,
        );
    }

    /**
     * @param  list<string>  $errors
     */
    public static function failure(array $errors): self
    {
        return new self(false, errors: $errors);
    }
}
