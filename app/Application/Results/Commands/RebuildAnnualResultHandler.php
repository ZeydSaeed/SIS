<?php

namespace App\Application\Results\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Results\Results\RebuildAnnualResultResult;
use App\Application\Results\Support\AnnualResultRebuildPlanner;
use App\Application\Results\Support\AnnualResultRebuildWriter;
use App\Domain\Results\Exceptions\TermResultEnrollmentNotFoundException;
use App\Domain\Results\Repositories\AnnualResultRepositoryInterface;
use App\Domain\Results\Support\TermResultIdempotencyGuard;

final class RebuildAnnualResultHandler implements CommandHandler
{
    private const COMMAND_NAME = 'RebuildAnnualResult';

    public function __construct(
        private readonly AnnualResultRepositoryInterface $annualResults,
        private readonly AnnualResultRebuildPlanner $planner,
        private readonly AnnualResultRebuildWriter $writer,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): RebuildAnnualResultResult
    {
        assert($command instanceof RebuildAnnualResultCommand);

        $idempotencyKey = TermResultIdempotencyGuard::requireKey($command->idempotencyKey);
        $mode = $this->planner->resolveMode($command->mode);

        $cached = $this->idempotency->find($idempotencyKey, self::COMMAND_NAME);
        if ($cached !== null) {
            return RebuildAnnualResultResult::fromIdempotency(
                (int) $cached['annual_result_id'],
                (int) $cached['result_version'],
                isset($cached['average_weighted_total']) ? (string) $cached['average_weighted_total'] : null,
                (bool) ($cached['incomplete'] ?? false),
                (bool) ($cached['unchanged'] ?? false),
                (string) $cached['mode'],
            );
        }

        $enrollment = $this->annualResults->findEnrollmentIdentity(
            $command->enrollmentId,
            $command->schoolId,
            $command->academicYearId,
        );
        if ($enrollment === null) {
            throw TermResultEnrollmentNotFoundException::forId($command->enrollmentId);
        }

        [$calculation, $current] = $this->planner->plan($command, $mode);

        if ($current !== null && $current->sourceFingerprint === $calculation->sourceFingerprint) {
            return $this->writer->writeUnchanged($command, $current, $mode, $idempotencyKey);
        }

        return $this->writer->writeChanged($command, $enrollment, $calculation, $mode, $idempotencyKey);
    }
}
