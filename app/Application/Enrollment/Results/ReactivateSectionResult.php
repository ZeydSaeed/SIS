<?php

namespace App\Application\Enrollment\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class ReactivateSectionResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $sectionId = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $sectionId): self
    {
        return new self(true, $sectionId);
    }

    public static function fromIdempotency(int $sectionId): self
    {
        return new self(true, $sectionId, fromIdempotencyCache: true);
    }
}
