<?php

namespace App\Application\Vocational\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class DeactivateTrackResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $trackId = null,
        array $errors = [],
        array $warnings = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    public static function success(int $trackId): self
    {
        return new self(true, $trackId);
    }

    public static function fromIdempotency(int $trackId): self
    {
        return new self(true, $trackId, fromIdempotencyCache: true);
    }
}
