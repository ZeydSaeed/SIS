<?php

namespace App\Application\Communication\Commands;

use App\Application\Communication\Contracts\OutboundMessagePort;
use App\Application\Communication\Results\MarkMessageSentResult;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Communication\Events\MessageSent;
use App\Domain\Communication\Repositories\MessageRepositoryInterface;
use App\Domain\Communication\Support\CommunicationIdempotencyGuard;
use App\Domain\Communication\ValueObjects\MessageStatus;

final class MarkMessageSentHandler implements CommandHandler
{
    private const COMMAND_NAME = 'MarkMessageSent';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly MessageRepositoryInterface $messages,
        private readonly OutboundMessagePort $outbound,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): MarkMessageSentResult
    {
        assert($command instanceof MarkMessageSentCommand);
        $key = CommunicationIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return MarkMessageSentResult::fromIdempotency((int) $cached['message_id']);
        }

        $snapshot = $this->messages->findByIdForSchool($command->schoolId, $command->messageId);
        if ($snapshot === null) {
            return MarkMessageSentResult::failure(['communication.message_not_found']);
        }
        if ($snapshot->status !== MessageStatus::Queued) {
            return MarkMessageSentResult::failure(['communication.message_not_queued']);
        }

        $delivery = $this->outbound->deliver($snapshot);
        if (! ($delivery['ok'] ?? false)) {
            return MarkMessageSentResult::failure([
                $delivery['error'] ?? 'communication.outbound_delivery_failed',
            ]);
        }

        $sentAt = (new \DateTimeImmutable)->format('Y-m-d H:i:s');
        $ok = $this->unitOfWork->transaction(function () use ($command, $key, $sentAt): bool {
            $updated = $this->messages->markSent(
                $command->schoolId,
                $command->messageId,
                MessageStatus::Sent,
                $sentAt,
            );
            if (! $updated) {
                return false;
            }
            $this->outbox->stage(new MessageSent(
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
            return MarkMessageSentResult::failure(['communication.message_mark_sent_failed']);
        }

        return MarkMessageSentResult::success($command->messageId);
    }
}
