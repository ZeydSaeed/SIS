<?php

namespace App\Application\Results\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class IssueTranscriptResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $transcriptId = null,
        public ?int $transcriptVersion = null,
        public ?string $transcriptNumber = null,
        public ?string $payloadHash = null,
        array $errors = [],
        array $warnings = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    public static function success(
        int $transcriptId,
        int $transcriptVersion,
        string $transcriptNumber,
        string $payloadHash,
    ): self {
        return new self(true, $transcriptId, $transcriptVersion, $transcriptNumber, $payloadHash);
    }

    public static function fromIdempotency(
        int $transcriptId,
        int $transcriptVersion,
        string $transcriptNumber,
        string $payloadHash,
    ): self {
        return new self(
            true,
            $transcriptId,
            $transcriptVersion,
            $transcriptNumber,
            $payloadHash,
            fromIdempotencyCache: true,
        );
    }
}
