<?php

namespace App\Application\Communication\Commands;

use App\Application\Communication\Results\CreateNotificationJobResult;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Communication\Events\NotificationJobCreated;
use App\Domain\Communication\Repositories\NotificationJobRepositoryInterface;
use App\Domain\Communication\Support\CommunicationIdempotencyGuard;
use App\Domain\Communication\ValueObjects\NotificationJobStatus;

final class CreateNotificationJobHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CreateNotificationJob';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly NotificationJobRepositoryInterface $jobs,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CreateNotificationJobResult
    {
        assert($command instanceof CreateNotificationJobCommand);
        $key = CommunicationIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return CreateNotificationJobResult::fromIdempotency((int) $cached['notification_job_id']);
        }

        if ($command->templateId < 1 || ! $this->jobs->templateBelongsToSchool($command->schoolId, $command->templateId)) {
            return CreateNotificationJobResult::failure(['communication.template_not_found']);
        }
        if ($command->totalCount < 0) {
            return CreateNotificationJobResult::failure(['communication.job_total_count_invalid']);
        }
        if ($command->targetFilter === []) {
            return CreateNotificationJobResult::failure(['communication.job_target_filter_invalid']);
        }

        $id = $this->unitOfWork->transaction(function () use ($command, $key): int {
            $id = $this->jobs->create(
                $command->schoolId,
                $command->templateId,
                $command->targetFilter,
                $command->totalCount,
                NotificationJobStatus::Open,
                $key,
                $command->createdBy,
                (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
            );
            $this->outbox->stage(new NotificationJobCreated(
                $id,
                $command->schoolId,
                $command->templateId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['notification_job_id' => $id]);

            return $id;
        });

        return CreateNotificationJobResult::success($id);
    }
}
