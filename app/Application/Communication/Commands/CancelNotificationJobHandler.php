<?php

namespace App\Application\Communication\Commands;

use App\Application\Communication\Results\CancelNotificationJobResult;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Communication\Events\NotificationJobCancelled;
use App\Domain\Communication\Repositories\NotificationJobRepositoryInterface;
use App\Domain\Communication\Support\CommunicationIdempotencyGuard;
use App\Domain\Communication\ValueObjects\NotificationJobStatus;

final class CancelNotificationJobHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CancelNotificationJob';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly NotificationJobRepositoryInterface $jobs,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CancelNotificationJobResult
    {
        assert($command instanceof CancelNotificationJobCommand);
        $key = CommunicationIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return CancelNotificationJobResult::fromIdempotency((int) $cached['notification_job_id']);
        }

        $job = $this->jobs->findByIdForSchool($command->schoolId, $command->notificationJobId);
        if ($job === null) {
            return CancelNotificationJobResult::failure(['communication.job_not_found']);
        }
        if ($job->status !== NotificationJobStatus::Open) {
            return CancelNotificationJobResult::failure(['communication.job_not_open']);
        }

        $ok = $this->unitOfWork->transaction(function () use ($command, $key): bool {
            $updated = $this->jobs->markCancelled(
                $command->schoolId,
                $command->notificationJobId,
                (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
            );
            if (! $updated) {
                return false;
            }
            $this->outbox->stage(new NotificationJobCancelled(
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
            return CancelNotificationJobResult::failure(['communication.job_cancel_failed']);
        }

        return CancelNotificationJobResult::success($command->notificationJobId);
    }
}
