<?php

namespace App\Application\Hr\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Hr\Results\DeactivateJobPositionResult;
use App\Domain\Hr\Events\JobPositionDeactivated;
use App\Domain\Hr\Repositories\HrRepositoryInterface;
use App\Domain\Hr\Support\HrIdempotencyGuard;
use App\Domain\Hr\ValueObjects\JobPositionStatus;

final class DeactivateJobPositionHandler implements CommandHandler
{
    private const COMMAND_NAME = 'DeactivateJobPosition';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly HrRepositoryInterface $hr,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): DeactivateJobPositionResult
    {
        assert($command instanceof DeactivateJobPositionCommand);
        $key = HrIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return DeactivateJobPositionResult::fromIdempotency((int) $cached['job_position_id']);
        }

        if ($this->hr->findJobPosition($command->schoolId, $command->jobPositionId) === null) {
            return DeactivateJobPositionResult::failure(['hr.job_position_not_found']);
        }

        $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');
        $this->unitOfWork->transaction(function () use ($command, $key, $at): void {
            $this->hr->setJobPositionStatus(
                $command->schoolId,
                $command->jobPositionId,
                JobPositionStatus::Inactive,
                $at,
            );
            $this->outbox->stage(new JobPositionDeactivated(
                $command->jobPositionId,
                $command->schoolId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, [
                'job_position_id' => $command->jobPositionId,
            ]);
        });

        return DeactivateJobPositionResult::success($command->jobPositionId);
    }
}
