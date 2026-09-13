<?php

namespace App\Application\Curriculum\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class DeactivateSubjectResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $subjectId = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $subjectId): self
    {
        return new self(true, $subjectId);
    }

    public static function fromIdempotency(int $subjectId): self
    {
        return new self(true, $subjectId, fromIdempotencyCache: true);
    }

    /** @param  list<string>  $errors */
    public static function failure(array $errors): self
    {
        return new self(false, null, $errors);
    }
}
