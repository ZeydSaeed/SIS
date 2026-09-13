<?php

namespace App\Application\Vocational\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class DeactivateWorkshopResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $workshopId = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $workshopId): self
    {
        return new self(true, $workshopId);
    }

    public static function fromIdempotency(int $workshopId): self
    {
        return new self(true, $workshopId, fromIdempotencyCache: true);
    }

    /** @param  list<string>  $errors */
    public static function failure(array $errors): self
    {
        return new self(false, null, $errors);
    }
}
