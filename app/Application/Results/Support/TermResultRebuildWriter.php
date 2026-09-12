<?php

namespace App\Application\Results\Support;

use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Results\Commands\RebuildTermResultCommand;
use App\Application\Results\Results\RebuildTermResultResult;
use App\Domain\Results\Data\CurrentTermResultSnapshot;
use App\Domain\Results\Data\PersistOperationalTermResultData;
use App\Domain\Results\Data\TermResultCalculation;
use App\Domain\Results\Events\TermResultRebuilt;
use App\Domain\Results\Repositories\TermResultRepositoryInterface;
use App\Domain\Results\ValueObjects\TermResultRebuildMode;

final class TermResultRebuildWriter
{
    private const COMMAND_NAME = 'RebuildTermResult';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly TermResultRepositoryInterface $termResults,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function writeUnchanged(
        RebuildTermResultCommand $command,
        CurrentTermResultSnapshot $current,
        TermResultRebuildMode $mode,
        string $idempotencyKey,
    ): RebuildTermResultResult {
        $this->unitOfWork->transaction(function () use ($command, $current, $idempotencyKey, $mode): void {
            $this->stage($command, $current->id, $current->resultVersion, $mode, true, $current->weightedTotal);
            $this->idempotency->store($idempotencyKey, self::COMMAND_NAME, [
                'term_result_id' => $current->id,
                'result_version' => $current->resultVersion,
                'weighted_total' => $current->weightedTotal,
                'incomplete' => $current->incomplete,
                'unchanged' => true,
                'mode' => $mode->value,
            ]);
        });

        return RebuildTermResultResult::success(
            $current->id,
            $current->resultVersion,
            $current->weightedTotal,
            $current->incomplete,
            true,
            $mode->value,
        );
    }

    /**
     * @param  array{student_id:int, academic_year_id:int}  $enrollment
     */
    public function writeChanged(
        RebuildTermResultCommand $command,
        array $enrollment,
        TermResultCalculation $calculation,
        TermResultRebuildMode $mode,
        string $idempotencyKey,
    ): RebuildTermResultResult {
        $version = $this->termResults->nextResultVersion(
            $command->schoolId,
            $command->enrollmentId,
            $command->termId,
            $command->subjectId,
        );
        $at = now()->toIso8601String();

        $termResultId = $this->unitOfWork->transaction(function () use (
            $command,
            $enrollment,
            $calculation,
            $version,
            $at,
            $idempotencyKey,
            $mode,
        ): int {
            $data = new PersistOperationalTermResultData(
                schoolId: $command->schoolId,
                enrollmentId: $command->enrollmentId,
                studentId: $enrollment['student_id'],
                academicYearId: $command->academicYearId,
                termId: $command->termId,
                subjectId: $command->subjectId,
                resultVersion: $version,
                weightedTotal: $calculation->weightedTotal,
                passFail: $calculation->passFail,
                incomplete: $calculation->incomplete,
                sourceFingerprint: $calculation->sourceFingerprint,
                calculationVersion: 1,
                policyPin: $calculation->policyPin,
                calculatedAt: $at,
                correlationId: $command->correlationId,
                createdBy: $command->createdBy,
            );

            $id = $mode === TermResultRebuildMode::Official
                ? $this->termResults->insertOfficial($data)
                : $this->termResults->insertOperational($data);

            $this->stage($command, $id, $version, $mode, false, $calculation->weightedTotal);
            $this->idempotency->store($idempotencyKey, self::COMMAND_NAME, [
                'term_result_id' => $id,
                'result_version' => $version,
                'weighted_total' => $calculation->weightedTotal,
                'incomplete' => $calculation->incomplete,
                'unchanged' => false,
                'mode' => $mode->value,
            ]);

            return $id;
        });

        return RebuildTermResultResult::success(
            $termResultId,
            $version,
            $calculation->weightedTotal,
            $calculation->incomplete,
            false,
            $mode->value,
        );
    }

    private function stage(
        RebuildTermResultCommand $command,
        int $termResultId,
        int $resultVersion,
        TermResultRebuildMode $mode,
        bool $unchanged,
        ?string $weightedTotal,
    ): void {
        $this->outbox->stage(new TermResultRebuilt(
            termResultId: $termResultId,
            schoolId: $command->schoolId,
            enrollmentId: $command->enrollmentId,
            academicYearId: $command->academicYearId,
            termId: $command->termId,
            subjectId: $command->subjectId,
            resultVersion: $resultVersion,
            mode: $mode->value,
            unchanged: $unchanged,
            weightedTotal: $weightedTotal,
            occurredAt: new \DateTimeImmutable,
        ));
    }
}
