<?php

namespace App\Application\Hr\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Hr\Results\ReactivateEmployeeResult;
use App\Domain\Hr\Events\EmployeeReactivated;
use App\Domain\Hr\Repositories\HrRepositoryInterface;
use App\Domain\Hr\Support\HrIdempotencyGuard;
use App\Domain\Hr\ValueObjects\EmployeeStatus;

final class ReactivateEmployeeHandler implements CommandHandler
{
    private const COMMAND_NAME = 'ReactivateEmployee';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly HrRepositoryInterface $hr,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): ReactivateEmployeeResult
    {
        assert($command instanceof ReactivateEmployeeCommand);
        $key = HrIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return ReactivateEmployeeResult::fromIdempotency((int) $cached['employee_id']);
        }

        if ($this->hr->findEmployeeInSchool($command->schoolId, $command->employeeId) === null) {
            return ReactivateEmployeeResult::failure(['hr.employee_not_found']);
        }

        $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');
        $this->unitOfWork->transaction(function () use ($command, $key, $at): void {
            $this->hr->reactivateEmployee(
                $command->schoolId,
                $command->employeeId,
                EmployeeStatus::Active,
                $at,
            );
            $this->outbox->stage(new EmployeeReactivated(
                $command->employeeId,
                $command->schoolId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, [
                'employee_id' => $command->employeeId,
            ]);
        });

        return ReactivateEmployeeResult::success($command->employeeId);
    }
}
