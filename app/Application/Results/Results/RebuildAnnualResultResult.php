<?php

namespace App\Application\Results\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class RebuildAnnualResultResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $annualResultId = null,
        public ?int $resultVersion = null,
        public ?string $averageWeightedTotal = null,
        public bool $incomplete = false,
        public bool $unchanged = false,
        public ?string $mode = null,
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
        bool $unchanged,
        string $mode,
    ): self {
        return new self(true, $annualResultId, $resultVersion, $averageWeightedTotal, $incomplete, $unchanged, $mode);
    }

    public static function fromIdempotency(
        int $annualResultId,
        int $resultVersion,
        ?string $averageWeightedTotal,
        bool $incomplete,
        bool $unchanged,
        string $mode,
    ): self {
        return new self(
            true,
            $annualResultId,
            $resultVersion,
            $averageWeightedTotal,
            $incomplete,
            $unchanged,
            $mode,
            fromIdempotencyCache: true,
        );
    }
}
