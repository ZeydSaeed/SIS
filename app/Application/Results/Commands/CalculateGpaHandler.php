<?php

namespace App\Application\Results\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Results\Results\CalculateGpaResult;
use App\Domain\Results\Data\PersistOperationalGpaResultData;
use App\Domain\Results\Events\GpaCalculated;
use App\Domain\Results\Exceptions\GpaOfficialAnnualMissingException;
use App\Domain\Results\Exceptions\TermResultEnrollmentNotFoundException;
use App\Domain\Results\Repositories\GpaResultRepositoryInterface;
use App\Domain\Results\Support\TermResultIdempotencyGuard;

final class CalculateGpaHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CalculateGpa';

    private const SCALE = 'PERCENT_100';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly GpaResultRepositoryInterface $gpaResults,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CalculateGpaResult
    {
        assert($command instanceof CalculateGpaCommand);

        $idempotencyKey = TermResultIdempotencyGuard::requireKey($command->idempotencyKey);

        $cached = $this->idempotency->find($idempotencyKey, self::COMMAND_NAME);
        if ($cached !== null) {
            return CalculateGpaResult::fromIdempotency(
                (int) $cached['gpa_result_id'],
                (int) $cached['result_version'],
                isset($cached['gpa_value']) ? (string) $cached['gpa_value'] : null,
                (bool) ($cached['incomplete'] ?? false),
            );
        }

        $enrollment = $this->gpaResults->findEnrollmentIdentity(
            $command->enrollmentId,
            $command->schoolId,
            $command->academicYearId,
        );
        if ($enrollment === null) {
            throw TermResultEnrollmentNotFoundException::forId($command->enrollmentId);
        }

        $annual = $this->gpaResults->findOfficialAnnualSource(
            $command->schoolId,
            $command->enrollmentId,
            $command->academicYearId,
        );
        if ($annual === null) {
            throw GpaOfficialAnnualMissingException::forIdentity();
        }

        $fingerprint = hash('sha256', $annual->annualResultId.'|'.$annual->sourceFingerprint.'|gpa-ops');
        $version = $this->gpaResults->nextResultVersion(
            $command->schoolId,
            $command->enrollmentId,
            $command->academicYearId,
        );
        $at = now()->toIso8601String();

        $gpaResultId = $this->unitOfWork->transaction(function () use (
            $command,
            $enrollment,
            $annual,
            $fingerprint,
            $version,
            $at,
            $idempotencyKey,
        ): int {
            $id = $this->gpaResults->insertOperational(new PersistOperationalGpaResultData(
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
                    'mode' => 'operational',
                    'scale' => self::SCALE,
                    'source' => 'annual_results.is_current_official',
                    'source_annual_result_id' => $annual->annualResultId,
                ],
                calculatedAt: $at,
                correlationId: $command->correlationId,
                createdBy: $command->createdBy,
            ));

            $this->outbox->stage(new GpaCalculated(
                gpaResultId: $id,
                schoolId: $command->schoolId,
                enrollmentId: $command->enrollmentId,
                studentId: $enrollment['student_id'],
                academicYearId: $command->academicYearId,
                resultVersion: $version,
                gpaValue: $annual->averageWeightedTotal,
                scaleCode: self::SCALE,
                occurredAt: new \DateTimeImmutable,
            ));

            $this->idempotency->store($idempotencyKey, self::COMMAND_NAME, [
                'gpa_result_id' => $id,
                'result_version' => $version,
                'gpa_value' => $annual->averageWeightedTotal,
                'incomplete' => $annual->incomplete,
            ]);

            return $id;
        });

        return CalculateGpaResult::success(
            $gpaResultId,
            $version,
            $annual->averageWeightedTotal,
            $annual->incomplete,
        );
    }
}
