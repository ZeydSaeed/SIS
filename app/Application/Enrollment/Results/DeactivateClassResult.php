<?php

namespace App\Application\Enrollment\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class DeactivateClassResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $classId = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $classId): self
    {
        return new self(true, $classId);
    }

    public static function fromIdempotency(int $classId): self
    {
        return new self(true, $classId, fromIdempotencyCache: true);
    }
}
