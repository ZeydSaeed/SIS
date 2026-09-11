<?php

namespace App\Application\Graduation\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Graduation\Contracts\GraduationAuthorityPort;
use App\Application\Graduation\Results\IssueAwardResult;
use App\Domain\Graduation\Events\AwardIssued;
use App\Domain\Graduation\Exceptions\GraduationBusinessConflictException;
use App\Domain\Graduation\Repositories\GraduationWriteRepositoryInterface;
use App\Domain\Graduation\Support\GraduationIdempotencyGuard;
use App\Domain\Graduation\ValueObjects\GraduationAction;

final class IssueAwardHandler implements CommandHandler
{
    public const COMMAND_NAME = 'Graduation.IssueAward';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly GraduationWriteRepositoryInterface $graduation,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
        private readonly GraduationAuthorityPort $authority,
    ) {}

    public function handle(Command $command): IssueAwardResult
    {
        assert($command instanceof IssueAwardCommand);

        $this->authority->assertCan(
            GraduationAction::IssueAward,
            $command->actorUserId,
            $command->schoolId,
        );

        $fingerprint = GraduationIdempotencyGuard::fingerprint(self::COMMAND_NAME, $command->schoolId, [
            'schema_version' => 1,
            'enrollment_id' => $command->enrollmentId,
            'approval_id' => $command->approvalId,
        ]);

        $payload = $this->unitOfWork->transaction(function () use ($command, $fingerprint): array {
            $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
            if ($cached !== null) {
                GraduationIdempotencyGuard::assertFingerprintMatch($cached, $fingerprint);

                return [
                    '_replay' => true,
                    'award_id' => (int) $cached['award_id'],
                    'award_version_id' => (int) $cached['award_version_id'],
                ];
            }

            $approval = $this->graduation->findApprovedApproval($command->approvalId, $command->schoolId);
            if ($approval === null) {
                throw GraduationBusinessConflictException::invalidState('Approved graduation approval not found.');
            }
            if ((int) $approval['enrollment_id'] !== $command->enrollmentId) {
                throw GraduationBusinessConflictException::invalidState('Approval enrollment mismatch.');
            }

            $identity = $this->graduation->findEnrollmentIdentity($command->enrollmentId, $command->schoolId);
            if ($identity === null) {
                throw GraduationBusinessConflictException::invalidState('Enrollment not found.');
            }

            $ids = $this->graduation->insertAwardWithVersion(
                $command->schoolId,
                $command->enrollmentId,
                $identity['student_id'],
                $identity['academic_year_id'],
                $command->approvalId,
                (int) $approval['completion_outcome_version_id'],
                $command->actorUserId,
                $command->correlationId,
            );

            $this->outbox->stage(new AwardIssued(
                awardId: $ids['award_id'],
                awardVersionId: $ids['award_version_id'],
                schoolId: $command->schoolId,
                enrollmentId: $command->enrollmentId,
                approvalId: $command->approvalId,
                issuedBy: $command->actorUserId,
                occurredAt: new \DateTimeImmutable,
            ), $command->correlationId);

            $resultPayload = GraduationIdempotencyGuard::withFingerprint([
                'award_id' => $ids['award_id'],
                'award_version_id' => $ids['award_version_id'],
            ], $fingerprint, $command->schoolId);

            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, $resultPayload);

            return $resultPayload + ['_replay' => false];
        });

        if ($payload['_replay'] === true) {
            return IssueAwardResult::fromIdempotency(
                (int) $payload['award_id'],
                (int) $payload['award_version_id'],
            );
        }

        return IssueAwardResult::success(
            (int) $payload['award_id'],
            (int) $payload['award_version_id'],
        );
    }
}
