<?php

namespace App\Application\Organization\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Organization\Results\DeleteBranchResult;
use App\Domain\Organization\Events\BranchChanged;
use App\Domain\Organization\Repositories\BranchStructureRepositoryInterface;
use App\Domain\Organization\Services\BranchStructureGuard;
use App\Domain\Organization\Support\OrganizationIdempotencyGuard;

/** Delete a branch = archive (status 3, مؤرشف); its rows stay for history. */
final class DeleteBranchHandler implements CommandHandler
{
    private const COMMAND_NAME = 'DeleteBranch';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly BranchStructureRepositoryInterface $structure,
        private readonly BranchStructureGuard $guard,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): DeleteBranchResult
    {
        assert($command instanceof DeleteBranchCommand);
        $key = OrganizationIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return DeleteBranchResult::fromIdempotency((int) $cached['id']);
        }

        $error = $this->guard->deleteBranchRejection($command->schoolId, $command->branchId);
        if ($error !== null) {
            return DeleteBranchResult::failure([$error]);
        }

        $id = $this->unitOfWork->transaction(function () use ($command, $key): int {
            $id = $command->branchId;
            $this->structure->setBranchStatus($id, BranchStructureRepositoryInterface::ARCHIVED);
            $this->outbox->stage(new BranchChanged($id, $command->schoolId, 'deleted', new \DateTimeImmutable));
            $this->idempotency->store($key, self::COMMAND_NAME, ['id' => $id]);

            return $id;
        });

        return DeleteBranchResult::success($id);
    }
}
