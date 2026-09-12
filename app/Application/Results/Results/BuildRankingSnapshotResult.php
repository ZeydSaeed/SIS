<?php

namespace App\Application\Results\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class BuildRankingSnapshotResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $rankingSnapshotId = null,
        public ?int $snapshotVersion = null,
        public int $participantCount = 0,
        array $errors = [],
        array $warnings = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    public static function success(int $rankingSnapshotId, int $snapshotVersion, int $participantCount): self
    {
        return new self(true, $rankingSnapshotId, $snapshotVersion, $participantCount);
    }

    public static function fromIdempotency(int $rankingSnapshotId, int $snapshotVersion, int $participantCount): self
    {
        return new self(true, $rankingSnapshotId, $snapshotVersion, $participantCount, fromIdempotencyCache: true);
    }
}
