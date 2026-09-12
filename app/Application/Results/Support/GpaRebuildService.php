<?php

namespace App\Application\Results\Support;

use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Results\Commands\RebuildGpaCommand;
use App\Application\Results\Results\RebuildGpaResult;
use App\Domain\Results\Data\CurrentGpaResultSnapshot;
use App\Domain\Results\Data\OfficialAnnualGpaSource;
use App\Domain\Results\Data\PersistOperationalGpaResultData;
use App\Domain\Results\Events\GpaRebuilt;
use App\Domain\Results\Exceptions\GpaOfficialAnnualMissingException;
use App\Domain\Results\Exceptions\InvalidTermResultRebuildModeException;
use App\Domain\Results\Repositories\GpaResultRepositoryInterface;
use App\Domain\Results\ValueObjects\TermResultRebuildMode;

final class GpaRebuildService
{
    private const COMMAND_NAME = 'RebuildGpa';

    private const SCALE = 'PERCENT_100';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly GpaResultRepositoryInterface $gpaResults,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function resolveMode(string $mode): TermResultRebuildMode
    {
        return TermResultRebuildMode::tryFrom($mode)
            ?? throw InvalidTermResultRebuildModeException::forValue($mode);
    }

    /**
     * @param  array{student_id:int, academic_year_id:int}  $enrollment
     */
    public function rebuild(
        RebuildGpaCommand $command,
        array $enrollment,
        TermResultRebuildMode $mode,
        string $idempotencyKey,
    ): RebuildGpaResult {
        $annual = $this->gpaResults->findOfficialAnnualSource(
            $command->schoolId,
            $command->enrollmentId,
            $command->academicYearId,
        );
        if ($annual === null) {
            throw GpaOfficialAnnualMissingException::forIdentity();
        }

        $suffix = $mode === TermResultRebuildMode::Official ? 'gpa-official' : 'gpa-ops';
        $fingerprint = hash('sha256', $annual->annualResultId.'|'.$annual->sourceFingerprint.'|'.$suffix);

        $current = $mode === TermResultRebuildMode::Official
            ? $this->gpaResults->findCurrentOfficial($command->schoolId, $command->enrollmentId, $command->academicYearId)
            : $this->gpaResults->findCurrentOperational($command->schoolId, $command->enrollmentId, $command->academicYearId);

        if ($current !== null && $current->sourceFingerprint === $fingerprint) {
            return $this->writeUnchanged($command, $current, $mode, $idempotencyKey);
        }

        return $this->writeChanged($command, $enrollment, $annual, $fingerprint, $mode, $idempotencyKey);
    }

    private function writeUnchanged(
        RebuildGpaCommand $command,
        CurrentGpaResultSnapshot $current,
        TermResultRebuildMode $mode,
        string $idempotencyKey,
    ): RebuildGpaResult {
        $this->unitOfWork->transaction(function () use ($command, $current, $mode, $idempotencyKey): void {
            $this->outbox->stage(new GpaRebuilt(
                gpaResultId: $current->id,
                schoolId: $command->schoolId,
                enrollmentId: $command->enrollmentId,
                academicYearId: $command->academicYearId,
                resultVersion: $current->resultVersion,
                mode: $mode->value,
                unchanged: true,
                gpaValue: $current->gpaValue,
                occurredAt: new \DateTimeImmutable,
            ));
            $this->idempotency->store($idempotencyKey, self::COMMAND_NAME, [
                'gpa_result_id' => $current->id,
                'result_version' => $current->resultVersion,
                'gpa_value' => $current->gpaValue,
                'incomplete' => $current->incomplete,
                'unchanged' => true,
                'mode' => $mode->value,
            ]);
        });

        return RebuildGpaResult::success(
            $current->id,
            $current->resultVersion,
            $current->gpaValue,
            $current->incomplete,
            true,
            $mode->value,
        );
    }

    /**
     * @param  array{student_id:int, academic_year_id:int}  $enrollment
     */
    private function writeChanged(
        RebuildGpaCommand $command,
        array $enrollment,
        OfficialAnnualGpaSource $annual,
        string $fingerprint,
        TermResultRebuildMode $mode,
        string $idempotencyKey,
    ): RebuildGpaResult {
        $version = $this->gpaResults->nextResultVersion(
            $command->schoolId,
            $command->enrollmentId,
            $command->academicYearId,
        );
        $at = now()->toIso8601String();

        $id = $this->unitOfWork->transaction(function () use (
            $command,
            $enrollment,
            $annual,
            $fingerprint,
            $version,
            $at,
            $mode,
            $idempotencyKey,
        ): int {
            $data = new PersistOperationalGpaResultData(
                schoolId: $command->schoolId,
                enrollmentId: $command->enrollmentId,
                studentId: $enrollment['student_id'],
                academicYearId: $command->academicYearId,
                resultVersion: $version,
                gpaValue: $annual->averageWeightedTotal,
                scaleCode: self::SCALE,
                sourceAnnualResultId: $annual->annualResultId,
                incomplete: $annual->incomplete,
                sourceFingerprint: $fingerprint,
                calculationVersion: 1,
                policyPin: [
                    'mode' => $mode->value,
                    'scale' => self::SCALE,
                    'source' => 'annual_results.is_current_official',
                    'source_annual_result_id' => $annual->annualResultId,
                ],
                calculatedAt: $at,
                correlationId: $command->correlationId,
                createdBy: $command->createdBy,
            );

            $gpaId = $mode === TermResultRebuildMode::Official
                ? $this->gpaResults->insertOfficial($data)
                : $this->gpaResults->insertOperational($data);

            $this->outbox->stage(new GpaRebuilt(
                gpaResultId: $gpaId,
                schoolId: $command->schoolId,
                enrollmentId: $command->enrollmentId,
                academicYearId: $command->academicYearId,
                resultVersion: $version,
                mode: $mode->value,
                unchanged: false,
                gpaValue: $annual->averageWeightedTotal,
                occurredAt: new \DateTimeImmutable,
            ));

            $this->idempotency->store($idempotencyKey, self::COMMAND_NAME, [
                'gpa_result_id' => $gpaId,
                'result_version' => $version,
                'gpa_value' => $annual->averageWeightedTotal,
                'incomplete' => $annual->incomplete,
                'unchanged' => false,
                'mode' => $mode->value,
            ]);

            return $gpaId;
        });

        return RebuildGpaResult::success(
            $id,
            $version,
            $annual->averageWeightedTotal,
            $annual->incomplete,
            false,
            $mode->value,
        );
    }
}
