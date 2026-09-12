<?php

namespace App\Application\Results\Support;

use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Results\Commands\RebuildAnnualResultCommand;
use App\Application\Results\Results\RebuildAnnualResultResult;
use App\Domain\Results\Data\AnnualResultCalculation;
use App\Domain\Results\Data\CurrentAnnualResultSnapshot;
use App\Domain\Results\Data\PersistOperationalAnnualResultData;
use App\Domain\Results\Events\AnnualResultRebuilt;
use App\Domain\Results\Repositories\AnnualResultRepositoryInterface;
use App\Domain\Results\ValueObjects\TermResultRebuildMode;

final class AnnualResultRebuildWriter
{
    private const COMMAND_NAME = 'RebuildAnnualResult';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly AnnualResultRepositoryInterface $annualResults,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function writeUnchanged(
        RebuildAnnualResultCommand $command,
        CurrentAnnualResultSnapshot $current,
        TermResultRebuildMode $mode,
        string $idempotencyKey,
    ): RebuildAnnualResultResult {
        $this->unitOfWork->transaction(function () use ($command, $current, $idempotencyKey, $mode): void {
            $this->stage($command, $current->id, $current->resultVersion, $mode, true, $current->averageWeightedTotal);
            $this->idempotency->store($idempotencyKey, self::COMMAND_NAME, [
                'annual_result_id' => $current->id,
                'result_version' => $current->resultVersion,
                'average_weighted_total' => $current->averageWeightedTotal,
                'incomplete' => $current->incomplete,
                'unchanged' => true,
                'mode' => $mode->value,
            ]);
        });

        return RebuildAnnualResultResult::success(
            $current->id,
            $current->resultVersion,
            $current->averageWeightedTotal,
            $current->incomplete,
            true,
            $mode->value,
        );
    }

    /**
     * @param  array{student_id:int, academic_year_id:int}  $enrollment
     */
    public function writeChanged(
        RebuildAnnualResultCommand $command,
        array $enrollment,
        AnnualResultCalculation $calculation,
        TermResultRebuildMode $mode,
        string $idempotencyKey,
    ): RebuildAnnualResultResult {
        $version = $this->annualResults->nextResultVersion(
            $command->schoolId,
            $command->enrollmentId,
            $command->academicYearId,
        );
        $at = now()->toIso8601String();

        $annualResultId = $this->unitOfWork->transaction(function () use (
            $command,
            $enrollment,
            $calculation,
            $version,
            $at,
            $idempotencyKey,
            $mode,
        ): int {
            $data = new PersistOperationalAnnualResultData(
                schoolId: $command->schoolId,
                enrollmentId: $command->enrollmentId,
                studentId: $enrollment['student_id'],
                academicYearId: $command->academicYearId,
                resultVersion: $version,
                subjectsCounted: $calculation->subjectsCounted,
                subjectsPassed: $calculation->subjectsPassed,
                subjectsIncomplete: $calculation->subjectsIncomplete,
                averageWeightedTotal: $calculation->averageWeightedTotal,
                incomplete: $calculation->incomplete,
                sourceFingerprint: $calculation->sourceFingerprint,
                calculationVersion: 1,
                policyPin: $calculation->policyPin,
                calculatedAt: $at,
                correlationId: $command->correlationId,
                createdBy: $command->createdBy,
            );

            $id = $mode === TermResultRebuildMode::Official
                ? $this->annualResults->insertOfficial($data)
                : $this->annualResults->insertOperational($data);

            $this->stage($command, $id, $version, $mode, false, $calculation->averageWeightedTotal);
            $this->idempotency->store($idempotencyKey, self::COMMAND_NAME, [
                'annual_result_id' => $id,
                'result_version' => $version,
                'average_weighted_total' => $calculation->averageWeightedTotal,
                'incomplete' => $calculation->incomplete,
                'unchanged' => false,
                'mode' => $mode->value,
            ]);

            return $id;
        });

        return RebuildAnnualResultResult::success(
            $annualResultId,
            $version,
            $calculation->averageWeightedTotal,
            $calculation->incomplete,
            false,
            $mode->value,
        );
    }

    private function stage(
        RebuildAnnualResultCommand $command,
        int $annualResultId,
        int $resultVersion,
        TermResultRebuildMode $mode,
        bool $unchanged,
        ?string $averageWeightedTotal,
    ): void {
        $this->outbox->stage(new AnnualResultRebuilt(
            annualResultId: $annualResultId,
            schoolId: $command->schoolId,
            enrollmentId: $command->enrollmentId,
            academicYearId: $command->academicYearId,
            resultVersion: $resultVersion,
            mode: $mode->value,
            unchanged: $unchanged,
            averageWeightedTotal: $averageWeightedTotal,
            occurredAt: new \DateTimeImmutable,
        ));
    }
}
