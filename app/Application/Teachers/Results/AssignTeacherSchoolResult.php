<?php

namespace App\Application\Teachers\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class AssignTeacherSchoolResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $teacherId = null,
        public ?int $teacherSchoolId = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $teacherId, int $teacherSchoolId): self
    {
        return new self(true, $teacherId, $teacherSchoolId);
    }

    public static function fromIdempotency(int $teacherId, int $teacherSchoolId): self
    {
        return new self(true, $teacherId, $teacherSchoolId, fromIdempotencyCache: true);
    }

    /** @param  list<string>  $errors */
    public static function failure(array $errors): self
    {
        return new self(false, null, null, $errors);
    }
}
