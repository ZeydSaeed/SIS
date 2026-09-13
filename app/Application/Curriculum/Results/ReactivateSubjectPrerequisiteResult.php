<?php

namespace App\Application\Curriculum\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class ReactivateSubjectPrerequisiteResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $prerequisiteId = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $prerequisiteId): self
    {
        return new self(true, $prerequisiteId);
    }

    public static function fromIdempotency(int $prerequisiteId): self
    {
        return new self(true, $prerequisiteId, fromIdempotencyCache: true);
    }

    /** @param  list<string>  $errors */
    public static function failure(array $errors): self
    {
        return new self(false, null, $errors);
    }
}
