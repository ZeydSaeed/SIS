<?php

namespace App\Application\Imports\Results;

use App\Application\Shared\Results\ApplicationResult;

/** «استيراد Excel» write outcome — the batch id (one subclass per command). */
abstract readonly class ImportResult extends ApplicationResult
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
