<?php

namespace App\Application\Communication\Commands;

use App\Application\Communication\Results\CancelMessageResult;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Communication\Events\MessageCancelled;
use App\Domain\Communication\Repositories\MessageRepositoryInterface;
use App\Domain\Communication\Support\CommunicationIdempotencyGuard;
use App\Domain\Communication\ValueObjects\MessageStatus;

final class CancelMessageHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CancelMessage';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly MessageRepositoryInterface $messages,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CancelMessageResult
    {
        assert($command instanceof CancelMessageCommand);
        $key = CommunicationIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return CancelMessageResult::fromIdempotency((int) $cached['message_id']);
        }

        $snapshot = $this->messages->findByIdForSchool($command->schoolId, $command->messageId);
        if ($snapshot === null) {
            return CancelMessageResult::failure(['communication.message_not_found']);
        }
        if ($snapshot->status !== MessageStatus::Queued) {
            return CancelMessageResult::failure(['communication.message_not_queued']);
        }

        $ok = $this->unitOfWork->transaction(function () use ($command, $key): bool {
            $updated = $this->messages->updateStatus(
                $command->schoolId,
                $command->messageId,
                MessageStatus::Queued,
                MessageStatus::Failed,
            );
            if (! $updated) {
                return false;
            }
            $this->outbox->stage(new MessageCancelled(
                $command->messageId,
                $command->schoolId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, [
                'message_id' => $command->messageId,
            ]);

            return true;
        });

        if (! $ok) {
            return CancelMessageResult::failure(['communication.message_cancel_failed']);
        }

        return CancelMessageResult::success($command->messageId);
    }
}
