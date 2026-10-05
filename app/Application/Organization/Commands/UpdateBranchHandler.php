<?php

namespace App\Application\Organization\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Organization\Results\UpdateBranchResult;
use App\Domain\Organization\Events\BranchChanged;
use App\Domain\Organization\Repositories\BranchStructureRepositoryInterface;
use App\Domain\Organization\Services\BranchStructureGuard;
use App\Domain\Organization\Support\OrganizationIdempotencyGuard;

/** Rename a branch / change its description. */
final class UpdateBranchHandler implements CommandHandler
{
    private const COMMAND_NAME = 'UpdateBranch';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly BranchStructureRepositoryInterface $structure,
        private readonly BranchStructureGuard $guard,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): UpdateBranchResult
    {
        assert($command instanceof UpdateBranchCommand);
        $key = OrganizationIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return UpdateBranchResult::fromIdempotency((int) $cached['id']);
        }

        $error = $this->guard->updateBranchRejection($command->schoolId, $command->branchId, $command->name);
        if ($error !== null) {
            return UpdateBranchResult::failure([$error]);
        }

        $id = $this->unitOfWork->transaction(function () use ($command, $key): int {
            $id = $command->branchId;
            $this->structure->updateBranch($id, trim($command->name), $command->description);
            $this->outbox->stage(new BranchChanged($id, $command->schoolId, 'updated', new \DateTimeImmutable));
            $this->idempotency->store($key, self::COMMAND_NAME, ['id' => $id]);

            return $id;
        });

        return UpdateBranchResult::success($id);
    }
}
