<?php

namespace App\Application\Audit\Commands;

use App\Application\Audit\Results\RecordLoginHistoryResult;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Audit\Events\LoginHistoryRecorded;
use App\Domain\Audit\Repositories\LoginHistoryRepositoryInterface;
use App\Domain\Audit\Support\AuditIdempotencyGuard;
use App\Domain\Audit\ValueObjects\LoginStatus;

final class RecordLoginHistoryHandler implements CommandHandler
{
    private const COMMAND_NAME = 'RecordLoginHistory';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly LoginHistoryRepositoryInterface $loginHistory,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): RecordLoginHistoryResult
    {
        assert($command instanceof RecordLoginHistoryCommand);
        $key = AuditIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return RecordLoginHistoryResult::fromIdempotency((int) $cached['login_history_id']);
        }

        if ($command->userId < 1) {
            return RecordLoginHistoryResult::failure(['audit.user_id_invalid']);
        }
        if (! LoginStatus::isValid($command->loginStatus)) {
            return RecordLoginHistoryResult::failure(['audit.login_status_invalid']);
        }

        $id = $this->unitOfWork->transaction(function () use ($command, $key): int {
            $id = $this->loginHistory->create(
                $command->schoolId,
                $command->userId,
                $command->ipAddress,
                $command->userAgent,
                $command->loginStatus,
                (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
            );
            $this->outbox->stage(new LoginHistoryRecorded(
                $id,
                $command->schoolId,
                $command->userId,
                $command->loginStatus,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['login_history_id' => $id]);

            return $id;
        });

        return RecordLoginHistoryResult::success($id);
    }
}
