<?php

namespace App\Application\Portal\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Portal\Results\UnlinkPortalPartyScopeResult;
use App\Domain\Portal\Events\PortalPartyScopeUnlinked;
use App\Domain\Portal\Repositories\PortalScopeRepositoryInterface;
use App\Domain\Portal\ValueObjects\PortalScopeType;

final class UnlinkPortalPartyScopeHandler implements CommandHandler
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly PortalScopeRepositoryInterface $scopes,
        private readonly OutboxRepository $outbox,
    ) {}

    public function handle(Command $command): UnlinkPortalPartyScopeResult
    {
        assert($command instanceof UnlinkPortalPartyScopeCommand);

        if (! in_array($command->scopeType, [PortalScopeType::STUDENT, PortalScopeType::GUARDIAN], true)) {
            return UnlinkPortalPartyScopeResult::failure(['portal.scopes.invalid_type']);
        }

        if (! $this->scopes->userExists($command->userId)) {
            return UnlinkPortalPartyScopeResult::failure(['portal.scopes.user_not_found']);
        }

        $wasPresent = false;
        $this->unitOfWork->transaction(function () use ($command, &$wasPresent): void {
            $wasPresent = $this->scopes->deleteScope(
                $command->userId,
                $command->scopeType,
                $command->scopeId,
            );
            $this->outbox->stage(new PortalPartyScopeUnlinked(
                $command->schoolId,
                $command->userId,
                $command->scopeType,
                $command->scopeId,
                $wasPresent,
                new \DateTimeImmutable,
            ));
        });

        return UnlinkPortalPartyScopeResult::success($wasPresent);
    }
}
