<?php

namespace App\Application\Results\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Results\Results\BuildRankingSnapshotResult;
use App\Domain\Results\Data\PersistRankingSnapshotData;
use App\Domain\Results\Events\RankingSnapshotBuilt;
use App\Domain\Results\Repositories\RankingSnapshotRepositoryInterface;
use App\Domain\Results\Services\DenseRankCalculator;
use App\Domain\Results\Support\TermResultIdempotencyGuard;

final class BuildRankingSnapshotHandler implements CommandHandler
{
    private const COMMAND_NAME = 'BuildRankingSnapshot';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly RankingSnapshotRepositoryInterface $rankings,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): BuildRankingSnapshotResult
    {
        assert($command instanceof BuildRankingSnapshotCommand);

        $idempotencyKey = TermResultIdempotencyGuard::requireKey($command->idempotencyKey);

        $cached = $this->idempotency->find($idempotencyKey, self::COMMAND_NAME);
        if ($cached !== null) {
            return BuildRankingSnapshotResult::fromIdempotency(
                (int) $cached['ranking_snapshot_id'],
                (int) $cached['snapshot_version'],
                (int) $cached['participant_count'],
            );
        }

        $participants = $this->rankings->listOfficialYearGpaParticipants(
            $command->schoolId,
            $command->academicYearId,
            $command->classId,
        );
        $ranked = DenseRankCalculator::rankByMetricDesc($participants);

        $parts = [];
        foreach ($ranked as $r) {
            $parts[] = $r->enrollmentId.':'.$r->gpaResultId.':'.($r->metricValue ?? 'null').':'.$r->rankPosition;
        }
        sort($parts);
        $fingerprint = hash('sha256', implode('|', $parts));

        $version = $this->rankings->nextSnapshotVersion(
            $command->schoolId,
            $command->academicYearId,
            $command->classId,
        );
        $at = now()->toIso8601String();

        $snapshotId = $this->unitOfWork->transaction(function () use (
            $command,
            $ranked,
            $fingerprint,
            $version,
            $at,
            $idempotencyKey,
        ): int {
            $id = $this->rankings->insertSnapshot(new PersistRankingSnapshotData(
                schoolId: $command->schoolId,
                academicYearId: $command->academicYearId,
                classId: $command->classId,
                snapshotVersion: $version,
                participantCount: count($ranked),
                sourceFingerprint: $fingerprint,
                policyPin: [
                    'metric' => 'YEAR_GPA_PERCENT_100',
                    'tie' => 'competition_rank_skip',
                    'source' => 'gpa_results.is_current_official',
                ],
                calculatedAt: $at,
                correlationId: $command->correlationId,
                createdBy: $command->createdBy,
                entries: $ranked,
            ));

            $this->outbox->stage(new RankingSnapshotBuilt(
                rankingSnapshotId: $id,
                schoolId: $command->schoolId,
                academicYearId: $command->academicYearId,
                classId: $command->classId,
                snapshotVersion: $version,
                participantCount: count($ranked),
                occurredAt: new \DateTimeImmutable,
            ));

            $this->idempotency->store($idempotencyKey, self::COMMAND_NAME, [
                'ranking_snapshot_id' => $id,
                'snapshot_version' => $version,
                'participant_count' => count($ranked),
            ]);

            return $id;
        });

        return BuildRankingSnapshotResult::success($snapshotId, $version, count($ranked));
    }
}
