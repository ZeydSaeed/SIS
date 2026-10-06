<?php

namespace App\Application\Organization\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Organization\Results\CreateDepartmentResult;
use App\Domain\Organization\Events\DepartmentChanged;
use App\Domain\Organization\Repositories\BranchStructureRepositoryInterface;
use App\Domain\Organization\Services\BranchStructureGuard;
use App\Domain\Organization\Support\OrganizationIdempotencyGuard;

/** Add a department (الاختصاص) to a branch of the current school. */
final class CreateDepartmentHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CreateDepartment';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly BranchStructureRepositoryInterface $structure,
        private readonly BranchStructureGuard $guard,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CreateDepartmentResult
    {
        assert($command instanceof CreateDepartmentCommand);
        $key = OrganizationIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return CreateDepartmentResult::fromIdempotency((int) $cached['id']);
        }

        $error = $this->guard->createDepartmentRejection($command->schoolId, $command->branchId, $command->name, $command->status);
        if ($error !== null) {
            return CreateDepartmentResult::failure([$error]);
        }

        $id = $this->unitOfWork->transaction(function () use ($command, $key): int {
            $id = $this->structure->createDepartment($command->schoolId, $command->branchId, trim($command->name), $command->description, $command->status);
            $this->outbox->stage(new DepartmentChanged($id, $command->schoolId, 'created', new \DateTimeImmutable));
            $this->idempotency->store($key, self::COMMAND_NAME, ['id' => $id]);

            return $id;
        });

        return CreateDepartmentResult::success($id);
    }
}
