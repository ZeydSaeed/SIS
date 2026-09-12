<?php

namespace App\Application\Teachers\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class AssignTeacherSubjectResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $assignmentId = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $assignmentId): self
    {
        return new self(true, $assignmentId);
    }

    public static function fromIdempotency(int $assignmentId): self
    {
        return new self(true, $assignmentId, fromIdempotencyCache: true);
    }

    /** @param  list<string>  $errors */
    public static function failure(array $errors): self
    {
        return new self(false, null, $errors);
    }
}
