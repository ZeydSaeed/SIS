<?php

namespace App\Application\Organization\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class CreateDirectorateResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $directorateId = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $directorateId): self
    {
        return new self(true, $directorateId);
    }

    public static function fromIdempotency(int $directorateId): self
    {
        return new self(true, $directorateId, fromIdempotencyCache: true);
    }

    /** @param  list<string>  $errors */
    public static function failure(array $errors): self
    {
        return new self(false, null, $errors);
    }
}
