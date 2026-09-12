<?php

namespace App\Application\Results\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class CalculateAnnualResultResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $annualResultId = null,
        public ?int $resultVersion = null,
        public ?string $averageWeightedTotal = null,
        public bool $incomplete = false,
        array $errors = [],
        array $warnings = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    public static function success(
        int $annualResultId,
        int $resultVersion,
        ?string $averageWeightedTotal,
        bool $incomplete,
    ): self {
        return new self(true, $annualResultId, $resultVersion, $averageWeightedTotal, $incomplete);
    }

    public static function fromIdempotency(
        int $annualResultId,
        int $resultVersion,
        ?string $averageWeightedTotal,
        bool $incomplete,
    ): self {
        return new self(
            true,
            $annualResultId,
            $resultVersion,
            $averageWeightedTotal,
            $incomplete,
            fromIdempotencyCache: true,
        );
    }
}
