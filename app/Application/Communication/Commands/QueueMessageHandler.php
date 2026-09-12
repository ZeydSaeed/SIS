<?php

namespace App\Application\Communication\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Communication\Results\QueueMessageResult;
use App\Domain\Communication\Events\MessageQueued;
use App\Domain\Communication\Repositories\MessageRepositoryInterface;
use App\Domain\Communication\Support\CommunicationIdempotencyGuard;
use App\Domain\Communication\Support\MessageRecipientTypes;
use App\Domain\Communication\ValueObjects\MessageStatus;
use App\Domain\Communication\ValueObjects\NotificationChannel;

final class QueueMessageHandler implements CommandHandler
{
    private const COMMAND_NAME = 'QueueMessage';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly MessageRepositoryInterface $messages,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): QueueMessageResult
    {
        assert($command instanceof QueueMessageCommand);
        $key = CommunicationIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return QueueMessageResult::fromIdempotency((int) $cached['message_id']);
        }

        $recipientType = trim($command->recipientType);
        if (! MessageRecipientTypes::isAllowed($recipientType)) {
            return QueueMessageResult::failure(['communication.recipient_type_invalid']);
        }
        if ($command->recipientId < 1) {
            return QueueMessageResult::failure(['communication.recipient_id_invalid']);
        }
        if (! NotificationChannel::isValid($command->channel)) {
            return QueueMessageResult::failure(['communication.channel_invalid']);
        }
        if (trim($command->body) === '') {
            return QueueMessageResult::failure(['communication.message_body_invalid']);
        }
        if ($command->templateId !== null && ! $this->messages->templateBelongsToSchool($command->schoolId, $command->templateId)) {
            return QueueMessageResult::failure(['communication.template_not_found']);
        }

        $subject = $command->subject !== null ? trim($command->subject) : null;
        if ($subject === '') {
            $subject = null;
        }

        $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');
        $id = $this->unitOfWork->transaction(function () use ($command, $key, $recipientType, $subject, $at): int {
            $id = $this->messages->create(
                $command->schoolId,
                $command->templateId,
                $recipientType,
                $command->recipientId,
                $command->channel,
                $subject,
                trim($command->body),
                MessageStatus::Queued,
                $key,
                $at,
            );
            $this->outbox->stage(new MessageQueued(
                $id,
                $command->schoolId,
                $recipientType,
                $command->recipientId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['message_id' => $id]);

            return $id;
        });

        return QueueMessageResult::success($id);
    }
}
