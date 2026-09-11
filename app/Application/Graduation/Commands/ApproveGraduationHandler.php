<?php

namespace App\Application\Graduation\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Graduation\Contracts\GraduationAuthorityPort;
use App\Application\Graduation\Results\ApproveGraduationResult;
use App\Domain\Graduation\Events\GraduationApproved;
use App\Domain\Graduation\Exceptions\GraduationBusinessConflictException;
use App\Domain\Graduation\Repositories\GraduationWriteRepositoryInterface;
use App\Domain\Graduation\Services\EvaluatorApproverSeparation;
use App\Domain\Graduation\Support\GraduationIdempotencyGuard;
use App\Domain\Graduation\ValueObjects\GraduationAction;

final class ApproveGraduationHandler implements CommandHandler
{
    public const COMMAND_NAME = 'Graduation.ApproveGraduation';

    /** Application convention aligned with EvaluateCompletionHandler: 2 = eligible. */
    private const ELIGIBLE_STATUS = 2;

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly GraduationWriteRepositoryInterface $graduation,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
        private readonly GraduationAuthorityPort $authority,
    ) {}

    public function handle(Command $command): ApproveGraduationResult
    {
        assert($command instanceof ApproveGraduationCommand);

        $this->authority->assertCan(
            GraduationAction::ApproveGraduation,
            $command->actorUserId,
            $command->schoolId,
        );

        $fingerprint = GraduationIdempotencyGuard::fingerprint(self::COMMAND_NAME, $command->schoolId, [
            'schema_version' => 1,
            'completion_outcome_version_id' => $command->completionOutcomeVersionId,
            'attempt_no' => $command->attemptNo,
            'decision_status' => 2,
        ]);

        $payload = $this->unitOfWork->transaction(function () use ($command, $fingerprint): array {
            $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
            if ($cached !== null) {
                GraduationIdempotencyGuard::assertFingerprintMatch($cached, $fingerprint);

                return [
                    '_replay' => true,
                    'approval_id' => (int) $cached['approval_id'],
                    'completion_outcome_version_id' => (int) $cached['completion_outcome_version_id'],
                ];
            }

            $version = $this->graduation->findCompletionOutcomeVersion(
                $command->completionOutcomeVersionId,
                $command->schoolId,
            );
            if ($version === null) {
                throw GraduationBusinessConflictException::invalidState('Completion outcome version not found.');
            }

            if ($version['eligibility_status'] !== self::ELIGIBLE_STATUS) {
                throw GraduationBusinessConflictException::invalidState(
                    'Completion outcome version is not eligible for graduation approval.',
                );
            }

            EvaluatorApproverSeparation::assertDistinct($version['created_by'], $command->actorUserId);

            $approvalId = $this->graduation->insertApproval(
                $command->schoolId,
                $version['enrollment_id'],
                $command->completionOutcomeVersionId,
                $command->attemptNo,
                2,
                $command->actorUserId,
                $command->decisionReasonRef,
                $command->correlationId,
            );

            $this->outbox->stage(new GraduationApproved(
                approvalId: $approvalId,
                schoolId: $command->schoolId,
                enrollmentId: $version['enrollment_id'],
                completionOutcomeVersionId: $command->completionOutcomeVersionId,
                decidedBy: $command->actorUserId,
                occurredAt: new \DateTimeImmutable,
            ), $command->correlationId);

            $resultPayload = GraduationIdempotencyGuard::withFingerprint([
                'approval_id' => $approvalId,
                'completion_outcome_version_id' => $command->completionOutcomeVersionId,
            ], $fingerprint, $command->schoolId);

            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, $resultPayload);

            return $resultPayload + ['_replay' => false];
        });

        if ($payload['_replay'] === true) {
            return ApproveGraduationResult::fromIdempotency(
                (int) $payload['approval_id'],
                (int) $payload['completion_outcome_version_id'],
            );
        }

        return ApproveGraduationResult::success(
            (int) $payload['approval_id'],
            (int) $payload['completion_outcome_version_id'],
        );
    }
}
