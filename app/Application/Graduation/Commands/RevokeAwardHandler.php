<?php

namespace App\Application\Graduation\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Graduation\Contracts\GraduationAuthorityPort;
use App\Application\Graduation\Results\RevokeAwardResult;
use App\Domain\Graduation\Events\AwardRevoked;
use App\Domain\Graduation\Exceptions\GraduationBusinessConflictException;
use App\Domain\Graduation\Repositories\GraduationWriteRepositoryInterface;
use App\Domain\Graduation\Support\GraduationIdempotencyGuard;
use App\Domain\Graduation\ValueObjects\GraduationAction;

/**
 * Reason ref is opaque — no invented reason catalog (HD-36-REASONS OPEN).
 */
final class RevokeAwardHandler implements CommandHandler
{
    public const COMMAND_NAME = 'Graduation.RevokeAward';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly GraduationWriteRepositoryInterface $graduation,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
        private readonly GraduationAuthorityPort $authority,
    ) {}

    public function handle(Command $command): RevokeAwardResult
    {
        assert($command instanceof RevokeAwardCommand);

        $this->authority->assertCan(
            GraduationAction::RevokeAward,
            $command->actorUserId,
            $command->schoolId,
        );

        if (trim($command->reasonRef) === '') {
            throw GraduationBusinessConflictException::invalidState('revocation_reason_ref is required (opaque; catalog OPEN).');
        }

        $fingerprint = GraduationIdempotencyGuard::fingerprint(self::COMMAND_NAME, $command->schoolId, [
            'schema_version' => 1,
            'award_version_id' => $command->awardVersionId,
            'reason_ref' => $command->reasonRef,
        ]);

        $payload = $this->unitOfWork->transaction(function () use ($command, $fingerprint): array {
            $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
            if ($cached !== null) {
                GraduationIdempotencyGuard::assertFingerprintMatch($cached, $fingerprint);

                return [
                    '_replay' => true,
                    'revocation_id' => (int) $cached['revocation_id'],
                    'award_version_id' => (int) $cached['award_version_id'],
                ];
            }

            $version = $this->graduation->findAwardVersion($command->awardVersionId, $command->schoolId);
            if ($version === null) {
                throw GraduationBusinessConflictException::invalidState('Award version not found.');
            }
            if ((int) $version['lifecycle_status'] === 3) {
                throw GraduationBusinessConflictException::invalidState('Award version already revoked.');
            }

            $revocationId = $this->graduation->revokeAwardVersion(
                $command->schoolId,
                $command->awardVersionId,
                $command->reasonRef,
                $command->actorUserId,
                $command->correlationId,
            );

            $this->outbox->stage(new AwardRevoked(
                revocationId: $revocationId,
                awardVersionId: $command->awardVersionId,
                schoolId: $command->schoolId,
                reasonRef: $command->reasonRef,
                revokedBy: $command->actorUserId,
                occurredAt: new \DateTimeImmutable,
            ), $command->correlationId);

            $resultPayload = GraduationIdempotencyGuard::withFingerprint([
                'revocation_id' => $revocationId,
                'award_version_id' => $command->awardVersionId,
            ], $fingerprint, $command->schoolId);

            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, $resultPayload);

            return $resultPayload + ['_replay' => false];
        });

        if ($payload['_replay'] === true) {
            return RevokeAwardResult::fromIdempotency(
                (int) $payload['revocation_id'],
                (int) $payload['award_version_id'],
            );
        }

        return RevokeAwardResult::success(
            (int) $payload['revocation_id'],
            (int) $payload['award_version_id'],
        );
    }
}
