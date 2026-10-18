<?php

namespace App\Application\Academic\Results;

use App\Application\Shared\Results\ApplicationResult;

/** «الاجازات» write outcome — the affected holiday id (one subclass per command). */
abstract readonly class HolidayResult extends ApplicationResult
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

    public static function failure(string $error): static
    {
        return new static(false, null, [$error]);
    }
}
