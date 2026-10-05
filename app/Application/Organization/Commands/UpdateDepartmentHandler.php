<?php

namespace App\Application\Organization\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Organization\Results\UpdateDepartmentResult;
use App\Domain\Organization\Events\DepartmentChanged;
use App\Domain\Organization\Repositories\BranchStructureRepositoryInterface;
use App\Domain\Organization\Services\BranchStructureGuard;
use App\Domain\Organization\Support\OrganizationIdempotencyGuard;

/** Rename a department, change its description or (when unused) its branch. */
final class UpdateDepartmentHandler implements CommandHandler
{
    private const COMMAND_NAME = 'UpdateDepartment';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly BranchStructureRepositoryInterface $structure,
        private readonly BranchStructureGuard $guard,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): UpdateDepartmentResult
    {
        assert($command instanceof UpdateDepartmentCommand);
        $key = OrganizationIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return UpdateDepartmentResult::fromIdempotency((int) $cached['id']);
        }

        $error = $this->guard->updateDepartmentRejection($command->schoolId, $command->departmentId, $command->branchId, $command->name);
        if ($error !== null) {
            return UpdateDepartmentResult::failure([$error]);
        }

        $id = $this->unitOfWork->transaction(function () use ($command, $key): int {
            $id = $command->departmentId;
            $this->structure->updateDepartment($id, $command->branchId, trim($command->name), $command->description);
            $this->outbox->stage(new DepartmentChanged($id, $command->schoolId, 'updated', new \DateTimeImmutable));
            $this->idempotency->store($key, self::COMMAND_NAME, ['id' => $id]);

            return $id;
        });

        return UpdateDepartmentResult::success($id);
    }
}
