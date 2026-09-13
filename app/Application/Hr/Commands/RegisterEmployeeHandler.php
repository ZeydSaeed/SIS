<?php

namespace App\Application\Hr\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Hr\Results\RegisterEmployeeResult;
use App\Domain\Hr\Events\EmployeeRegistered;
use App\Domain\Hr\Repositories\HrRepositoryInterface;
use App\Domain\Hr\Services\RegisterEmployeeGuard;
use App\Domain\Hr\Support\HrIdempotencyGuard;
use App\Domain\Hr\ValueObjects\EmployeeStatus;

final class RegisterEmployeeHandler implements CommandHandler
{
    private const COMMAND_NAME = 'RegisterEmployee';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly HrRepositoryInterface $hr,
        private readonly RegisterEmployeeGuard $guard,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): RegisterEmployeeResult
    {
        assert($command instanceof RegisterEmployeeCommand);
        $key = HrIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return RegisterEmployeeResult::fromIdempotency((int) $cached['employee_id']);
        }

        $number = strtoupper(trim($command->employeeNumber));
        $first = trim($command->firstName);
        $last = trim($command->lastName);
        $error = $this->guard->rejectionCode(
            $command->schoolId,
            $command->academicYearId,
            $number,
            $first,
            $last,
            $command->jobPositionId,
            $command->teacherId,
        );
        if ($error !== null) {
            return RegisterEmployeeResult::failure([$error]);
        }

        $nationalId = $command->nationalId !== null && trim($command->nationalId) !== ''
            ? trim($command->nationalId)
            : null;
        $fullName = trim($first.' '.$last);
        $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');
        $jobPositionId = $command->jobPositionId;
        $teacherId = $command->teacherId;

        $employeeId = $this->unitOfWork->transaction(function () use ($command, $key, $number, $first, $last, $fullName, $nationalId, $teacherId, $jobPositionId, $at): int {
            $employeeId = $this->hr->createEmployee(
                $command->schoolId,
                $number,
                $command->userId,
                $teacherId,
                $nationalId,
                $first,
                $last,
                $fullName,
                $command->hireDate,
                EmployeeStatus::Active,
                $at,
                $at,
            );
            $this->hr->assignSchool(
                $employeeId,
                $command->schoolId,
                $command->academicYearId,
                $jobPositionId,
                true,
                $at,
            );
            $this->outbox->stage(new EmployeeRegistered(
                $employeeId,
                $command->schoolId,
                $command->academicYearId,
                $number,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['employee_id' => $employeeId]);

            return $employeeId;
        });

        return RegisterEmployeeResult::success($employeeId);
    }
}
