<?php

namespace App\Application\Results\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Results\Contracts\ResultPublicationRepositoryInterface;
use App\Application\Results\Results\WithdrawResultPublicationResult;
use App\Domain\Results\Exceptions\ResultPublicationException;
use App\Domain\Results\Support\TermResultIdempotencyGuard;

/**
 * A repeated request (same key, same publication) replays instead of failing on the already-withdrawn publication;
 * the same key sent for another publication is refused, never replayed as the first one.
 */
final class WithdrawResultPublicationHandler implements CommandHandler
{
    private const COMMAND_NAME = 'WithdrawResultPublication';

    public function __construct(
        private readonly ResultPublicationRepositoryInterface $publications,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): WithdrawResultPublicationResult
    {
        assert($command instanceof WithdrawResultPublicationCommand);

        $key = TermResultIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            if ((int) $cached['publication_id'] !== $command->publicationId) {
                throw ResultPublicationException::idempotencyConflict();
            }

            return new WithdrawResultPublicationResult($command->publicationId, true);
        }

        $reason = trim($command->reason);
        if ($reason === '') {
            throw ResultPublicationException::reasonRequired();
        }
        if (! $this->publications->withdraw($command->schoolId, $command->publicationId, $command->actorUserId, $reason)) {
            throw ResultPublicationException::notFound($command->publicationId);
        }
        $this->idempotency->store($key, self::COMMAND_NAME, ['publication_id' => $command->publicationId]);

        return new WithdrawResultPublicationResult($command->publicationId);
    }
}
