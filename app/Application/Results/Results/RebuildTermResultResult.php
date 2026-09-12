<?php

namespace App\Application\Results\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class RebuildTermResultResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $termResultId = null,
        public ?int $resultVersion = null,
        public ?string $weightedTotal = null,
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
        int $termResultId,
        int $resultVersion,
        ?string $weightedTotal,
        bool $incomplete,
        bool $unchanged,
        string $mode,
    ): self {
        return new self(true, $termResultId, $resultVersion, $weightedTotal, $incomplete, $unchanged, $mode);
    }

    public static function fromIdempotency(
        int $termResultId,
        int $resultVersion,
        ?string $weightedTotal,
        bool $incomplete,
        bool $unchanged,
        string $mode,
    ): self {
        return new self(
            true,
            $termResultId,
            $resultVersion,
            $weightedTotal,
            $incomplete,
            $unchanged,
            $mode,
            fromIdempotencyCache: true,
        );
    }
}
