<?php

namespace App\Application\Results\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Results\Results\FinalizeTermResultResult;
use App\Domain\Results\Data\PersistOperationalTermResultData;
use App\Domain\Results\Events\TermResultFinalized;
use App\Domain\Results\Exceptions\TermResultDatasetIncompleteException;
use App\Domain\Results\Exceptions\TermResultEnrollmentNotFoundException;
use App\Domain\Results\Repositories\TermResultRepositoryInterface;
use App\Domain\Results\Services\TermResultCalculator;
use App\Domain\Results\Support\TermResultIdempotencyGuard;

final class FinalizeTermResultHandler implements CommandHandler
{
    private const COMMAND_NAME = 'FinalizeTermResult';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly TermResultRepositoryInterface $termResults,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): FinalizeTermResultResult
    {
        assert($command instanceof FinalizeTermResultCommand);

        $idempotencyKey = TermResultIdempotencyGuard::requireKey($command->idempotencyKey);

        $cached = $this->idempotency->find($idempotencyKey, self::COMMAND_NAME);
        if ($cached !== null) {
            return FinalizeTermResultResult::fromIdempotency(
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

        $contributions = $this->termResults->listOfficialContributions(
            $command->schoolId,
            $command->enrollmentId,
            $command->academicYearId,
            $command->termId,
            $command->subjectId,
        );

        $calculation = TermResultCalculator::calculateOfficial($contributions);
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
        ): int {
            $id = $this->termResults->insertOfficial(new PersistOperationalTermResultData(
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
            ));

            $this->outbox->stage(new TermResultFinalized(
                termResultId: $id,
                schoolId: $command->schoolId,
                enrollmentId: $command->enrollmentId,
                studentId: $enrollment['student_id'],
                academicYearId: $command->academicYearId,
                termId: $command->termId,
                subjectId: $command->subjectId,
                resultVersion: $version,
                weightedTotal: $calculation->weightedTotal,
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

        return FinalizeTermResultResult::success(
            $termResultId,
            $version,
            $calculation->weightedTotal,
            $calculation->incomplete,
        );
    }
}
