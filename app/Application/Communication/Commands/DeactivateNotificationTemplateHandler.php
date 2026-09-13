<?php

namespace App\Application\Communication\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Communication\Results\DeactivateNotificationTemplateResult;
use App\Domain\Communication\Events\NotificationTemplateDeactivated;
use App\Domain\Communication\Repositories\NotificationTemplateRepositoryInterface;
use App\Domain\Communication\Support\CommunicationIdempotencyGuard;

final class DeactivateNotificationTemplateHandler implements CommandHandler
{
    private const COMMAND_NAME = 'DeactivateNotificationTemplate';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly NotificationTemplateRepositoryInterface $templates,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): DeactivateNotificationTemplateResult
    {
        assert($command instanceof DeactivateNotificationTemplateCommand);
        $key = CommunicationIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return DeactivateNotificationTemplateResult::fromIdempotency((int) $cached['template_id']);
        }

        $row = $this->templates->find($command->schoolId, $command->templateId);
        if ($row === null) {
            return DeactivateNotificationTemplateResult::failure(['communication.template_not_found']);
        }
        if (! $row->isActive) {
            return DeactivateNotificationTemplateResult::failure(['communication.template_already_inactive']);
        }

        $this->unitOfWork->transaction(function () use ($command, $key): void {
            $this->templates->setActive($command->schoolId, $command->templateId, false);
            $this->outbox->stage(new NotificationTemplateDeactivated(
                $command->templateId,
                $command->schoolId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['template_id' => $command->templateId]);
        });

        return DeactivateNotificationTemplateResult::success($command->templateId);
    }
}
