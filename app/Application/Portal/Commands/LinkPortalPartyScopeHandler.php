<?php

namespace App\Application\Portal\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Portal\Results\LinkPortalPartyScopeResult;
use App\Application\Portal\Support\PortalScopeLinkRules;
use App\Domain\Portal\Events\PortalPartyScopeLinked;
use App\Domain\Portal\Repositories\PortalScopeRepositoryInterface;
use App\Domain\Portal\Support\PortalIdempotencyGuard;

final class LinkPortalPartyScopeHandler implements CommandHandler
{
    private const COMMAND_NAME = 'LinkPortalPartyScope';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly PortalScopeRepositoryInterface $scopes,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
        private readonly PortalScopeLinkRules $rules,
    ) {}

    public function handle(Command $command): LinkPortalPartyScopeResult
    {
        assert($command instanceof LinkPortalPartyScopeCommand);
        $key = PortalIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return LinkPortalPartyScopeResult::fromIdempotency((int) $cached['scope_row_id']);
        }

        $error = $this->rules->validate($command);
        if ($error !== null) {
            return LinkPortalPartyScopeResult::failure([$error]);
        }

        $existing = $this->scopes->findScopeId($command->userId, $command->scopeType, $command->scopeId);
        if ($existing !== null) {
            $this->idempotency->store($key, self::COMMAND_NAME, ['scope_row_id' => $existing]);

            return LinkPortalPartyScopeResult::fromIdempotency($existing);
        }

        $id = $this->unitOfWork->transaction(function () use ($command, $key): int {
            $id = $this->scopes->insertScope(
                $command->userId,
                $command->scopeType,
                $command->scopeId,
                (new \DateTimeImmutable)->format('Y-m-d H:i:s'),
            );
            $this->outbox->stage(new PortalPartyScopeLinked(
                $id,
                $command->schoolId,
                $command->userId,
                $command->scopeType,
                $command->scopeId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['scope_row_id' => $id]);

            return $id;
        });

        return LinkPortalPartyScopeResult::success($id);
    }
}
