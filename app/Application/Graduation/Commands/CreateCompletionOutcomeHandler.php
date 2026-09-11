<?php

namespace App\Application\Graduation\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Graduation\Contracts\GraduationAuthorityPort;
use App\Application\Graduation\Results\CreateCompletionOutcomeResult;
use App\Domain\Graduation\Events\CompletionOutcomeCreated;
use App\Domain\Graduation\Exceptions\GraduationBusinessConflictException;
use App\Domain\Graduation\Repositories\GraduationWriteRepositoryInterface;
use App\Domain\Graduation\Support\GraduationIdempotencyGuard;
use App\Domain\Graduation\ValueObjects\GraduationAction;

final class CreateCompletionOutcomeHandler implements CommandHandler
{
    public const COMMAND_NAME = 'Graduation.CreateCompletionOutcome';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly GraduationWriteRepositoryInterface $graduation,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
        private readonly GraduationAuthorityPort $authority,
    ) {}

    public function handle(Command $command): CreateCompletionOutcomeResult
    {
        assert($command instanceof CreateCompletionOutcomeCommand);

        $this->authority->assertCan(
            GraduationAction::CreateCompletionOutcome,
            $command->actorUserId,
            $command->schoolId,
        );

        $fingerprint = GraduationIdempotencyGuard::fingerprint(self::COMMAND_NAME, $command->schoolId, [
            'schema_version' => 1,
            'enrollment_id' => $command->enrollmentId,
        ]);

        $payload = $this->unitOfWork->transaction(function () use ($command, $fingerprint): array {
            $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
            if ($cached !== null) {
                GraduationIdempotencyGuard::assertFingerprintMatch($cached, $fingerprint);

                return [
                    '_replay' => true,
                    'completion_outcome_id' => (int) $cached['completion_outcome_id'],
                    'school_id' => (int) $cached['school_id'],
                    'enrollment_id' => (int) $cached['enrollment_id'],
                ];
            }

            $identity = $this->graduation->findEnrollmentIdentity($command->enrollmentId, $command->schoolId);
            if ($identity === null) {
                throw GraduationBusinessConflictException::invalidState('Enrollment not found for school scope.');
            }

            $outcomeId = $this->graduation->insertCompletionOutcome(
                $command->schoolId,
                $command->enrollmentId,
                $identity['student_id'],
                $identity['academic_year_id'],
                $command->actorUserId,
            );

            $this->outbox->stage(new CompletionOutcomeCreated(
                completionOutcomeId: $outcomeId,
                schoolId: $command->schoolId,
                enrollmentId: $command->enrollmentId,
                studentId: $identity['student_id'],
                academicYearId: $identity['academic_year_id'],
                createdBy: $command->actorUserId,
                occurredAt: new \DateTimeImmutable,
            ), $command->correlationId);

            $resultPayload = GraduationIdempotencyGuard::withFingerprint([
                'completion_outcome_id' => $outcomeId,
                'school_id' => $command->schoolId,
                'enrollment_id' => $command->enrollmentId,
            ], $fingerprint, $command->schoolId);

            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, $resultPayload);

            return $resultPayload + ['_replay' => false];
        });

        if ($payload['_replay'] === true) {
            return CreateCompletionOutcomeResult::fromIdempotency(
                (int) $payload['completion_outcome_id'],
                (int) $payload['school_id'],
                (int) $payload['enrollment_id'],
            );
        }

        return CreateCompletionOutcomeResult::success(
            (int) $payload['completion_outcome_id'],
            (int) $payload['school_id'],
            (int) $payload['enrollment_id'],
        );
    }
}
