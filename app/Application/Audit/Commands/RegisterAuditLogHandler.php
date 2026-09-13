<?php

namespace App\Application\Audit\Commands;

use App\Application\Audit\Results\RegisterAuditLogResult;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Audit\Events\AuditLogRegistered;
use App\Domain\Audit\Repositories\AuditLogRepositoryInterface;
use App\Domain\Audit\Services\RegisterAuditLogGuard;
use App\Domain\Audit\Support\AuditIdempotencyGuard;

final class RegisterAuditLogHandler implements CommandHandler
{
    private const COMMAND_NAME = 'RegisterAuditLog';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly AuditLogRepositoryInterface $auditLogs,
        private readonly RegisterAuditLogGuard $guard,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): RegisterAuditLogResult
    {
        assert($command instanceof RegisterAuditLogCommand);
        $key = AuditIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return RegisterAuditLogResult::fromIdempotency((int) $cached['audit_log_id']);
        }

        $action = trim($command->action);
        $entityType = trim($command->entityType);
        $error = $this->guard->rejectionCode($action, $entityType);
        if ($error !== null) {
            return RegisterAuditLogResult::failure([$error]);
        }

        $id = $this->unitOfWork->transaction(function () use ($command, $key, $action, $entityType): int {
            $id = $this->auditLogs->create(
                $command->schoolId,
                $command->userId,
                $action,
                $entityType,
                $command->entityId,
                $command->oldValues,
                $command->newValues,
                $command->ipAddress,
                $command->userAgent,
                $command->correlationId,
                (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
            );
            $this->outbox->stage(new AuditLogRegistered(
                $id,
                $command->schoolId,
                $action,
                $entityType,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['audit_log_id' => $id]);

            return $id;
        });

        return RegisterAuditLogResult::success($id);
    }
}
