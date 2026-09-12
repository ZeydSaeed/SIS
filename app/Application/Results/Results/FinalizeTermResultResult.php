<?php

namespace App\Application\Results\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class FinalizeTermResultResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $termResultId = null,
        public ?int $resultVersion = null,
        public ?string $weightedTotal = null,
        public bool $incomplete = false,
        array $errors = [],
        array $warnings = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    public static function success(
        int $termResultId,
        int $resultVersion,
        ?string $weightedTotal,
        bool $incomplete,
    ): self {
        return new self(true, $termResultId, $resultVersion, $weightedTotal, $incomplete);
    }

    public static function fromIdempotency(
        int $termResultId,
        int $resultVersion,
        ?string $weightedTotal,
        bool $incomplete,
    ): self {
        return new self(
            true,
            $termResultId,
            $resultVersion,
            $weightedTotal,
            $incomplete,
            fromIdempotencyCache: true,
        );
    }
}
