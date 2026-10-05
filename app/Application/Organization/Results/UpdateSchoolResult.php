<?php

namespace App\Application\Organization\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class UpdateSchoolResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $schoolId = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $schoolId): self
    {
        return new self(true, $schoolId);
    }

    public static function fromIdempotency(int $schoolId): self
    {
        return new self(true, $schoolId, fromIdempotencyCache: true);
    }

    /** @param  list<string>  $errors */
    public static function failure(array $errors): self
    {
        return new self(false, null, $errors);
    }
}
