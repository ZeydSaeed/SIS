<?php

namespace App\Application\Results\Support;

use App\Application\Results\Commands\RebuildTermResultCommand;
use App\Domain\Results\Data\CurrentTermResultSnapshot;
use App\Domain\Results\Data\TermResultCalculation;
use App\Domain\Results\Exceptions\InvalidTermResultRebuildModeException;
use App\Domain\Results\Exceptions\TermResultDatasetIncompleteException;
use App\Domain\Results\Repositories\TermResultRepositoryInterface;
use App\Domain\Results\Services\TermResultCalculator;
use App\Domain\Results\ValueObjects\TermResultRebuildMode;

/**
 * Plans a term-result rebuild from LIVE grades (DL-016).
 */
final class TermResultRebuildPlanner
{
    public function __construct(
        private readonly TermResultRepositoryInterface $termResults,
    ) {}

    public function resolveMode(string $mode): TermResultRebuildMode
    {
        return TermResultRebuildMode::tryFrom($mode)
            ?? throw InvalidTermResultRebuildModeException::forValue($mode);
    }

    /**
     * @return array{0:TermResultCalculation,1:?CurrentTermResultSnapshot}
     */
    public function plan(RebuildTermResultCommand $command, TermResultRebuildMode $mode): array
    {
        if ($mode === TermResultRebuildMode::Official) {
            $this->assertOfficialDatasetComplete($command);
            $contributions = $this->termResults->listOfficialContributions(
                $command->schoolId,
                $command->enrollmentId,
                $command->academicYearId,
                $command->termId,
                $command->subjectId,
            );
            $calculation = TermResultCalculator::calculateOfficial($contributions);
            $current = $this->termResults->findCurrentOfficial(
                $command->schoolId,
                $command->enrollmentId,
                $command->termId,
                $command->subjectId,
            );

            return [$calculation, $current];
        }

        $contributions = $this->termResults->listOperationalContributions(
            $command->schoolId,
            $command->enrollmentId,
            $command->academicYearId,
            $command->termId,
            $command->subjectId,
        );
        $calculation = TermResultCalculator::calculateOperational($contributions);
        $current = $this->termResults->findCurrentOperational(
            $command->schoolId,
            $command->enrollmentId,
            $command->termId,
            $command->subjectId,
        );

        return [$calculation, $current];
    }

    private function assertOfficialDatasetComplete(RebuildTermResultCommand $command): void
    {
        $requiredSessions = $this->termResults->listRequiredSessionIds(
            $command->schoolId,
            $command->enrollmentId,
            $command->academicYearId,
            $command->termId,
            $command->subjectId,
        );
        if ($requiredSessions === []) {
            throw TermResultDatasetIncompleteException::missingFinalizedGrades();
        }

        $finalizedCount = $this->termResults->countFinalizedCurrentGradesForSessions(
            $command->schoolId,
            $command->enrollmentId,
            $command->academicYearId,
            $requiredSessions,
        );
        if ($finalizedCount !== count($requiredSessions)) {
            throw TermResultDatasetIncompleteException::missingFinalizedGrades();
        }
    }
}
