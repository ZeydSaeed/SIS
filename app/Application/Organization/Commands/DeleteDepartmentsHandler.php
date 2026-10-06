<?php

namespace App\Application\Organization\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Organization\Results\DeleteDepartmentsResult;
use App\Domain\Organization\Events\DepartmentChanged;
use App\Domain\Organization\Repositories\BranchStructureRepositoryInterface;
use App\Domain\Organization\Services\BranchStructureGuard;
use App\Domain\Organization\Support\OrganizationIdempotencyGuard;

/** Delete departments = archive (status 3, مؤرشف), all or none; a department in use is refused. */
final class DeleteDepartmentsHandler implements CommandHandler
{
    private const COMMAND_NAME = 'DeleteDepartments';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly BranchStructureRepositoryInterface $structure,
        private readonly BranchStructureGuard $guard,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): DeleteDepartmentsResult
    {
        assert($command instanceof DeleteDepartmentsCommand);
        $key = OrganizationIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return DeleteDepartmentsResult::fromIdempotency((int) $cached['id']);
        }

        $ids = array_values(array_unique(array_map('intval', $command->departmentIds)));
        if ($ids === []) {
            return DeleteDepartmentsResult::failure(['organization.department_selection_empty']);
        }
        foreach ($ids as $departmentId) {
            $error = $this->guard->deleteDepartmentRejection($command->schoolId, $departmentId);
            if ($error !== null) {
                return DeleteDepartmentsResult::failure([$error]);
            }
        }

        $this->unitOfWork->transaction(function () use ($command, $ids, $key): void {
            $now = new \DateTimeImmutable;
            foreach ($ids as $departmentId) {
                $this->structure->setDepartmentStatus($departmentId, BranchStructureRepositoryInterface::ARCHIVED);
                $this->outbox->stage(new DepartmentChanged($departmentId, $command->schoolId, 'deleted', $now));
            }
            $this->idempotency->store($key, self::COMMAND_NAME, ['id' => $ids[0]]);
        });

        return DeleteDepartmentsResult::success($ids[0]);
    }
}
