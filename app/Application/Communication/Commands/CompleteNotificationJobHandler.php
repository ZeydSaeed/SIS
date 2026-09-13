<?php

namespace App\Application\Communication\Commands;

use App\Application\Communication\Results\CompleteNotificationJobResult;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Communication\Events\NotificationJobCompleted;
use App\Domain\Communication\Repositories\NotificationJobRepositoryInterface;
use App\Domain\Communication\Support\CommunicationIdempotencyGuard;
use App\Domain\Communication\ValueObjects\NotificationJobStatus;

final class CompleteNotificationJobHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CompleteNotificationJob';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly NotificationJobRepositoryInterface $jobs,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CompleteNotificationJobResult
    {
        assert($command instanceof CompleteNotificationJobCommand);
        $key = CommunicationIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return CompleteNotificationJobResult::fromIdempotency((int) $cached['notification_job_id']);
        }

        $job = $this->jobs->findByIdForSchool($command->schoolId, $command->notificationJobId);
        if ($job === null) {
            return CompleteNotificationJobResult::failure(['communication.job_not_found']);
        }
        if ($job->status !== NotificationJobStatus::Open) {
            return CompleteNotificationJobResult::failure(['communication.job_not_open']);
        }
        if ($command->sentCount < 0 || $command->sentCount > $job->totalCount) {
            return CompleteNotificationJobResult::failure(['communication.job_sent_count_invalid']);
        }

        $ok = $this->unitOfWork->transaction(function () use ($command, $key): bool {
            $updated = $this->jobs->markCompleted(
                $command->schoolId,
                $command->notificationJobId,
                $command->sentCount,
                (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
            );
            if (! $updated) {
                return false;
            }
            $this->outbox->stage(new NotificationJobCompleted(
                $command->notificationJobId,
                $command->schoolId,
                $command->sentCount,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, [
                'notification_job_id' => $command->notificationJobId,
            ]);

            return true;
        });

        if (! $ok) {
            return CompleteNotificationJobResult::failure(['communication.job_complete_failed']);
        }

        return CompleteNotificationJobResult::success($command->notificationJobId);
    }
}
