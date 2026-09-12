<?php

namespace App\Application\Vocational\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class DeactivateSpecializationResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $specializationId = null,
        array $errors = [],
        array $warnings = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    public static function success(int $specializationId): self
    {
        return new self(true, $specializationId);
    }

    public static function fromIdempotency(int $specializationId): self
    {
        return new self(true, $specializationId, fromIdempotencyCache: true);
    }
}
