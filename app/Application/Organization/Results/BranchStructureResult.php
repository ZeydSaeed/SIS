<?php

namespace App\Application\Organization\Results;

use App\Application\Shared\Results\ApplicationResult;

/** «الفروع والاختصاصات» write outcome — the affected branch / department id (one subclass per command). */
abstract readonly class BranchStructureResult extends ApplicationResult
{
    final protected function __construct(
        bool $success,
        public ?int $id = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $id): static
    {
        return new static(true, $id);
    }

    public static function fromIdempotency(int $id): static
    {
        return new static(true, $id, fromIdempotencyCache: true);
    }

    /** @param  list<string>  $errors */
    public static function failure(array $errors): static
    {
        return new static(false, null, $errors);
    }
}
