<?php

namespace App\Application\Results\Support;

use App\Application\Results\Commands\RebuildAnnualResultCommand;
use App\Domain\Results\Data\AnnualResultCalculation;
use App\Domain\Results\Data\CurrentAnnualResultSnapshot;
use App\Domain\Results\Exceptions\InvalidTermResultRebuildModeException;
use App\Domain\Results\Repositories\AnnualResultRepositoryInterface;
use App\Domain\Results\Services\AnnualResultCalculator;
use App\Domain\Results\ValueObjects\TermResultRebuildMode;

final class AnnualResultRebuildPlanner
{
    public function __construct(
        private readonly AnnualResultRepositoryInterface $annualResults,
    ) {}

    public function resolveMode(string $mode): TermResultRebuildMode
    {
        return TermResultRebuildMode::tryFrom($mode)
            ?? throw InvalidTermResultRebuildModeException::forValue($mode);
    }

    /**
     * @return array{0:AnnualResultCalculation,1:?CurrentAnnualResultSnapshot}
     */
    public function plan(RebuildAnnualResultCommand $command, TermResultRebuildMode $mode): array
    {
        if ($mode === TermResultRebuildMode::Official) {
            $rows = $this->annualResults->listCurrentOfficialTermRows(
                $command->schoolId,
                $command->enrollmentId,
                $command->academicYearId,
            );
            $calculation = AnnualResultCalculator::calculateOfficial($rows);
            $current = $this->annualResults->findCurrentOfficial(
                $command->schoolId,
                $command->enrollmentId,
                $command->academicYearId,
            );

            return [$calculation, $current];
        }

        $rows = $this->annualResults->listCurrentOperationalTermRows(
            $command->schoolId,
            $command->enrollmentId,
            $command->academicYearId,
        );
        $calculation = AnnualResultCalculator::calculateOperational($rows);
        $current = $this->annualResults->findCurrentOperational(
            $command->schoolId,
            $command->enrollmentId,
            $command->academicYearId,
        );

        return [$calculation, $current];
    }
}
