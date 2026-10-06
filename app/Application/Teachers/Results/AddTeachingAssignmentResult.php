<?php

namespace App\Application\Teachers\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class AddTeachingAssignmentResult extends ApplicationResult
{
    /** @param  list<string>  $errors */
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

    public static function failure(string $code): self
    {
        return new self(false, null, [$code]);
    }
}
