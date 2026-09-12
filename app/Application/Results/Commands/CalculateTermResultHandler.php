<?php

namespace App\Application\Results\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Results\Results\CalculateTermResultResult;
use App\Domain\Results\Data\PersistOperationalTermResultData;
use App\Domain\Results\Events\TermResultCalculated;
use App\Domain\Results\Exceptions\TermResultEnrollmentNotFoundException;
use App\Domain\Results\Repositories\TermResultRepositoryInterface;
use App\Domain\Results\Services\TermResultCalculator;
use App\Domain\Results\Support\TermResultIdempotencyGuard;

final class CalculateTermResultHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CalculateTermResult';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly TermResultRepositoryInterface $termResults,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CalculateTermResultResult
    {
        assert($command instanceof CalculateTermResultCommand);

        $idempotencyKey = TermResultIdempotencyGuard::requireKey($command->idempotencyKey);

        $cached = $this->idempotency->find($idempotencyKey, self::COMMAND_NAME);
        if ($cached !== null) {
            return CalculateTermResultResult::fromIdempotency(
                (int) $cached['term_result_id'],
                (int) $cached['result_version'],
                isset($cached['weighted_total']) ? (string) $cached['weighted_total'] : null,
                (bool) ($cached['incomplete'] ?? false),
            );
        }

        $enrollment = $this->termResults->findEnrollmentIdentity(
            $command->enrollmentId,
            $command->schoolId,
            $command->academicYearId,
        );
        if ($enrollment === null) {
            throw TermResultEnrollmentNotFoundException::forId($command->enrollmentId);
        }

        $contributions = $this->termResults->listOperationalContributions(
            $command->schoolId,
            $command->enrollmentId,
            $command->academicYearId,
            $command->termId,
            $command->subjectId,
        );

        $calculation = TermResultCalculator::calculateOperational($contributions);
        $version = $this->termResults->nextResultVersion(
            $command->schoolId,
            $command->enrollmentId,
            $command->termId,
            $command->subjectId,
        );
        $calculatedAt = now()->toIso8601String();

        $termResultId = $this->unitOfWork->transaction(function () use (
            $command,
            $enrollment,
            $calculation,
            $version,
            $calculatedAt,
            $idempotencyKey,
        ): int {
            $id = $this->termResults->insertOperational(new PersistOperationalTermResultData(
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
                calculatedAt: $calculatedAt,
                correlationId: $command->correlationId,
                createdBy: $command->createdBy,
            ));

            $this->outbox->stage(new TermResultCalculated(
                termResultId: $id,
                schoolId: $command->schoolId,
                enrollmentId: $command->enrollmentId,
                studentId: $enrollment['student_id'],
                academicYearId: $command->academicYearId,
                termId: $command->termId,
                subjectId: $command->subjectId,
                resultVersion: $version,
                weightedTotal: $calculation->weightedTotal,
                incomplete: $calculation->incomplete,
                occurredAt: new \DateTimeImmutable,
            ));

            $this->idempotency->store($idempotencyKey, self::COMMAND_NAME, [
                'term_result_id' => $id,
                'result_version' => $version,
                'weighted_total' => $calculation->weightedTotal,
                'incomplete' => $calculation->incomplete,
            ]);

            return $id;
        });

        return CalculateTermResultResult::success(
            $termResultId,
            $version,
            $calculation->weightedTotal,
            $calculation->incomplete,
        );
    }
}
