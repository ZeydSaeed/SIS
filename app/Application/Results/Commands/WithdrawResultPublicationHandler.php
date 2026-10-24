<?php

namespace App\Application\Results\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Results\Contracts\ResultPublicationRepositoryInterface;
use App\Application\Results\Results\WithdrawResultPublicationResult;
use App\Domain\Results\Exceptions\ResultPublicationException;
use App\Domain\Results\Support\TermResultIdempotencyGuard;

/** A repeated request (same key) replays instead of failing on the already-withdrawn publication. */
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
            return new WithdrawResultPublicationResult((int) $cached['publication_id'], true);
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
