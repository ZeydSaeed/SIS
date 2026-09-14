<?php

namespace App\Application\Communication\Commands;

use App\Application\Communication\Results\ReopenNotificationJobResult;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Communication\Events\NotificationJobReopened;
use App\Domain\Communication\Repositories\NotificationJobRepositoryInterface;
use App\Domain\Communication\Support\CommunicationIdempotencyGuard;
use App\Domain\Communication\ValueObjects\NotificationJobStatus;

final class ReopenNotificationJobHandler implements CommandHandler
{
    private const COMMAND_NAME = 'ReopenNotificationJob';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly NotificationJobRepositoryInterface $jobs,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): ReopenNotificationJobResult
    {
        assert($command instanceof ReopenNotificationJobCommand);
        $key = CommunicationIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return ReopenNotificationJobResult::fromIdempotency((int) $cached['notification_job_id']);
        }

        $job = $this->jobs->findByIdForSchool($command->schoolId, $command->notificationJobId);
        if ($job === null) {
            return ReopenNotificationJobResult::failure(['communication.job_not_found']);
        }
        if ($job->status === NotificationJobStatus::Open) {
            return ReopenNotificationJobResult::failure(['communication.job_already_open']);
        }
        if ($job->status !== NotificationJobStatus::Cancelled) {
            return ReopenNotificationJobResult::failure(['communication.job_not_reopenable']);
        }

        $ok = $this->unitOfWork->transaction(function () use ($command, $key): bool {
            if (! $this->jobs->markReopened($command->schoolId, $command->notificationJobId)) {
                return false;
            }

            $this->outbox->stage(new NotificationJobReopened(
                $command->notificationJobId,
                $command->schoolId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, [
                'notification_job_id' => $command->notificationJobId,
            ]);

            return true;
        });

        if (! $ok) {
            return ReopenNotificationJobResult::failure(['communication.job_reopen_failed']);
        }

        return ReopenNotificationJobResult::success($command->notificationJobId);
    }
}
