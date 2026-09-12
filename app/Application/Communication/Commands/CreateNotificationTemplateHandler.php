<?php

namespace App\Application\Communication\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Communication\Results\CreateNotificationTemplateResult;
use App\Domain\Communication\Events\NotificationTemplateCreated;
use App\Domain\Communication\Repositories\NotificationTemplateRepositoryInterface;
use App\Domain\Communication\Support\CommunicationIdempotencyGuard;
use App\Domain\Communication\ValueObjects\NotificationChannel;

final class CreateNotificationTemplateHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CreateNotificationTemplate';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly NotificationTemplateRepositoryInterface $templates,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CreateNotificationTemplateResult
    {
        assert($command instanceof CreateNotificationTemplateCommand);
        $key = CommunicationIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return CreateNotificationTemplateResult::fromIdempotency((int) $cached['template_id']);
        }

        $code = strtoupper(trim($command->code));
        if ($code === '' || strlen($code) > 50) {
            return CreateNotificationTemplateResult::failure(['communication.template_code_invalid']);
        }
        if (trim($command->name) === '') {
            return CreateNotificationTemplateResult::failure(['communication.template_name_invalid']);
        }
        if (! NotificationChannel::isValid($command->channel)) {
            return CreateNotificationTemplateResult::failure(['communication.channel_invalid']);
        }
        if (trim($command->bodyTemplate) === '') {
            return CreateNotificationTemplateResult::failure(['communication.body_template_invalid']);
        }
        if ($this->templates->findIdBySchoolAndCode($command->schoolId, $code) !== null) {
            return CreateNotificationTemplateResult::failure(['communication.template_code_duplicate']);
        }

        $subject = $command->subjectTemplate !== null ? trim($command->subjectTemplate) : null;
        if ($subject === '') {
            $subject = null;
        }

        $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');
        $id = $this->unitOfWork->transaction(function () use ($command, $key, $code, $subject, $at): int {
            $id = $this->templates->create(
                $command->schoolId,
                $code,
                trim($command->name),
                $command->channel,
                $subject,
                trim($command->bodyTemplate),
                $command->isActive,
                $at,
            );
            $this->outbox->stage(new NotificationTemplateCreated(
                $id,
                $command->schoolId,
                $code,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['template_id' => $id]);

            return $id;
        });

        return CreateNotificationTemplateResult::success($id);
    }
}
