<?php

namespace App\Application\Results\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class RebuildGpaResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $gpaResultId = null,
        public ?int $resultVersion = null,
        public ?string $gpaValue = null,
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
        int $gpaResultId,
        int $resultVersion,
        ?string $gpaValue,
        bool $incomplete,
        bool $unchanged,
        string $mode,
    ): self {
        return new self(true, $gpaResultId, $resultVersion, $gpaValue, $incomplete, $unchanged, $mode);
    }

    public static function fromIdempotency(
        int $gpaResultId,
        int $resultVersion,
        ?string $gpaValue,
        bool $incomplete,
        bool $unchanged,
        string $mode,
    ): self {
        return new self(
            true,
            $gpaResultId,
            $resultVersion,
            $gpaValue,
            $incomplete,
            $unchanged,
            $mode,
            fromIdempotencyCache: true,
        );
    }
}
