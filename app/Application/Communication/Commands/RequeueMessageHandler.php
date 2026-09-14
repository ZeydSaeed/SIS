<?php

namespace App\Application\Communication\Commands;

use App\Application\Communication\Results\RequeueMessageResult;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Communication\Events\MessageRequeued;
use App\Domain\Communication\Repositories\MessageRepositoryInterface;
use App\Domain\Communication\Support\CommunicationIdempotencyGuard;
use App\Domain\Communication\ValueObjects\MessageStatus;

final class RequeueMessageHandler implements CommandHandler
{
    private const COMMAND_NAME = 'RequeueMessage';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly MessageRepositoryInterface $messages,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): RequeueMessageResult
    {
        assert($command instanceof RequeueMessageCommand);
        $key = CommunicationIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return RequeueMessageResult::fromIdempotency((int) $cached['message_id']);
        }

        $snapshot = $this->messages->findByIdForSchool($command->schoolId, $command->messageId);
        if ($snapshot === null) {
            return RequeueMessageResult::failure(['communication.message_not_found']);
        }
        if ($snapshot->status !== MessageStatus::Failed) {
            return RequeueMessageResult::failure(['communication.message_not_failed']);
        }

        $ok = $this->unitOfWork->transaction(function () use ($command, $key): bool {
            $updated = $this->messages->updateStatus(
                $command->schoolId,
                $command->messageId,
                MessageStatus::Failed,
                MessageStatus::Queued,
                clearSentAt: true,
            );
            if (! $updated) {
                return false;
            }
            $this->outbox->stage(new MessageRequeued(
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
            return RequeueMessageResult::failure(['communication.message_requeue_failed']);
        }

        return RequeueMessageResult::success($command->messageId);
    }
}
