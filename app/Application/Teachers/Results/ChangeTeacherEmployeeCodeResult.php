<?php

namespace App\Application\Teachers\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class ChangeTeacherEmployeeCodeResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $teacherId = null,
        public ?string $employeeCode = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $teacherId, string $employeeCode): self
    {
        return new self(true, $teacherId, $employeeCode);
    }

    public static function fromIdempotency(int $teacherId, string $employeeCode): self
    {
        return new self(true, $teacherId, $employeeCode, fromIdempotencyCache: true);
    }

    /** @param  list<string>  $errors */
    public static function failure(array $errors): self
    {
        return new self(false, null, null, $errors);
    }
}
