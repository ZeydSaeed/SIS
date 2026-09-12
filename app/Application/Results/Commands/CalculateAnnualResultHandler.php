<?php

namespace App\Application\Results\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Results\Results\CalculateAnnualResultResult;
use App\Domain\Results\Data\PersistOperationalAnnualResultData;
use App\Domain\Results\Events\AnnualResultCalculated;
use App\Domain\Results\Exceptions\TermResultEnrollmentNotFoundException;
use App\Domain\Results\Repositories\AnnualResultRepositoryInterface;
use App\Domain\Results\Services\AnnualResultCalculator;
use App\Domain\Results\Support\TermResultIdempotencyGuard;

final class CalculateAnnualResultHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CalculateAnnualResult';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly AnnualResultRepositoryInterface $annualResults,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CalculateAnnualResultResult
    {
        assert($command instanceof CalculateAnnualResultCommand);

        $idempotencyKey = TermResultIdempotencyGuard::requireKey($command->idempotencyKey);

        $cached = $this->idempotency->find($idempotencyKey, self::COMMAND_NAME);
        if ($cached !== null) {
            return CalculateAnnualResultResult::fromIdempotency(
                (int) $cached['annual_result_id'],
                (int) $cached['result_version'],
                isset($cached['average_weighted_total']) ? (string) $cached['average_weighted_total'] : null,
                (bool) ($cached['incomplete'] ?? false),
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

        $rows = $this->annualResults->listCurrentOperationalTermRows(
            $command->schoolId,
            $command->enrollmentId,
            $command->academicYearId,
        );
        $calculation = AnnualResultCalculator::calculateOperational($rows);
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
        ): int {
            $id = $this->annualResults->insertOperational(new PersistOperationalAnnualResultData(
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
            ));

            $this->outbox->stage(new AnnualResultCalculated(
                annualResultId: $id,
                schoolId: $command->schoolId,
                enrollmentId: $command->enrollmentId,
                studentId: $enrollment['student_id'],
                academicYearId: $command->academicYearId,
                resultVersion: $version,
                averageWeightedTotal: $calculation->averageWeightedTotal,
                incomplete: $calculation->incomplete,
                occurredAt: new \DateTimeImmutable,
            ));

            $this->idempotency->store($idempotencyKey, self::COMMAND_NAME, [
                'annual_result_id' => $id,
                'result_version' => $version,
                'average_weighted_total' => $calculation->averageWeightedTotal,
                'incomplete' => $calculation->incomplete,
            ]);

            return $id;
        });

        return CalculateAnnualResultResult::success(
            $annualResultId,
            $version,
            $calculation->averageWeightedTotal,
            $calculation->incomplete,
        );
    }
}
