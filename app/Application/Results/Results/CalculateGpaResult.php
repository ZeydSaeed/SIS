<?php

namespace App\Application\Results\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class CalculateGpaResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $gpaResultId = null,
        public ?int $resultVersion = null,
        public ?string $gpaValue = null,
        public string $scaleCode = 'PERCENT_100',
        public bool $incomplete = false,
        array $errors = [],
        array $warnings = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    public static function success(
        int $gpaResultId,
        int $resultVersion,
        ?string $gpaValue,
        bool $incomplete,
    ): self {
        return new self(true, $gpaResultId, $resultVersion, $gpaValue, 'PERCENT_100', $incomplete);
    }

    public static function fromIdempotency(
        int $gpaResultId,
        int $resultVersion,
        ?string $gpaValue,
        bool $incomplete,
    ): self {
        return new self(
            true,
            $gpaResultId,
            $resultVersion,
            $gpaValue,
            'PERCENT_100',
            $incomplete,
            fromIdempotencyCache: true,
        );
    }
}
