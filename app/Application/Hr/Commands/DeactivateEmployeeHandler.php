<?php

namespace App\Application\Hr\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Hr\Results\DeactivateEmployeeResult;
use App\Domain\Hr\Events\EmployeeDeactivated;
use App\Domain\Hr\Repositories\HrRepositoryInterface;
use App\Domain\Hr\Support\HrIdempotencyGuard;
use App\Domain\Hr\ValueObjects\EmployeeStatus;

final class DeactivateEmployeeHandler implements CommandHandler
{
    private const COMMAND_NAME = 'DeactivateEmployee';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly HrRepositoryInterface $hr,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): DeactivateEmployeeResult
    {
        assert($command instanceof DeactivateEmployeeCommand);
        $key = HrIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return DeactivateEmployeeResult::fromIdempotency((int) $cached['employee_id']);
        }

        if ($this->hr->findEmployeeInSchool($command->schoolId, $command->employeeId) === null) {
            return DeactivateEmployeeResult::failure(['hr.employee_not_found']);
        }

        $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');
        $this->unitOfWork->transaction(function () use ($command, $key, $at): void {
            $this->hr->deactivateEmployee(
                $command->schoolId,
                $command->employeeId,
                EmployeeStatus::Inactive,
                $at,
            );
            $this->outbox->stage(new EmployeeDeactivated(
                $command->employeeId,
                $command->schoolId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, [
                'employee_id' => $command->employeeId,
            ]);
        });

        return DeactivateEmployeeResult::success($command->employeeId);
    }
}
