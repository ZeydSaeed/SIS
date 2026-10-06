<?php

namespace App\Application\Organization\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Organization\Results\CreateBranchResult;
use App\Domain\Organization\Events\BranchChanged;
use App\Domain\Organization\Repositories\BranchStructureRepositoryInterface;
use App\Domain\Organization\Services\BranchStructureGuard;
use App\Domain\Organization\Support\OrganizationIdempotencyGuard;

/** Add a branch (الفرع) to the current school. */
final class CreateBranchHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CreateBranch';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly BranchStructureRepositoryInterface $structure,
        private readonly BranchStructureGuard $guard,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CreateBranchResult
    {
        assert($command instanceof CreateBranchCommand);
        $key = OrganizationIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return CreateBranchResult::fromIdempotency((int) $cached['id']);
        }

        $error = $this->guard->createBranchRejection($command->schoolId, $command->name, $command->status);
        if ($error !== null) {
            return CreateBranchResult::failure([$error]);
        }

        $id = $this->unitOfWork->transaction(function () use ($command, $key): int {
            $id = $this->structure->createBranch($command->schoolId, trim($command->name), $command->description, $command->status);
            $this->outbox->stage(new BranchChanged($id, $command->schoolId, 'created', new \DateTimeImmutable));
            $this->idempotency->store($key, self::COMMAND_NAME, ['id' => $id]);

            return $id;
        });

        return CreateBranchResult::success($id);
    }
}
