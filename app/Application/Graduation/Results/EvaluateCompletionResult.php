<?php

namespace App\Application\Graduation\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class EvaluateCompletionResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $completionOutcomeId = null,
        public ?int $completionOutcomeVersionId = null,
        public ?int $eligibilityStatus = null,
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, [], [], $fromIdempotencyCache);
    }

    public static function success(int $outcomeId, int $versionId, int $eligibilityStatus): self
    {
        return new self(true, $outcomeId, $versionId, $eligibilityStatus);
    }

    public static function fromIdempotency(int $outcomeId, int $versionId, int $eligibilityStatus): self
    {
        return new self(true, $outcomeId, $versionId, $eligibilityStatus, fromIdempotencyCache: true);
    }
}
