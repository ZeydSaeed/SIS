<?php

namespace App\Application\Vocational\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class LinkSpecializationSubjectResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $linkId = null,
        array $errors = [],
        array $warnings = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    public static function success(int $linkId): self
    {
        return new self(true, $linkId);
    }

    public static function fromIdempotency(int $linkId): self
    {
        return new self(true, $linkId, fromIdempotencyCache: true);
    }
}
