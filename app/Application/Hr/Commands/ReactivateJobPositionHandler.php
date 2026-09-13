<?php

namespace App\Application\Hr\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Hr\Results\ReactivateJobPositionResult;
use App\Domain\Hr\Events\JobPositionReactivated;
use App\Domain\Hr\Repositories\HrRepositoryInterface;
use App\Domain\Hr\Support\HrIdempotencyGuard;
use App\Domain\Hr\ValueObjects\JobPositionStatus;

final class ReactivateJobPositionHandler implements CommandHandler
{
    private const COMMAND_NAME = 'ReactivateJobPosition';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly HrRepositoryInterface $hr,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): ReactivateJobPositionResult
    {
        assert($command instanceof ReactivateJobPositionCommand);
        $key = HrIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return ReactivateJobPositionResult::fromIdempotency((int) $cached['job_position_id']);
        }

        if ($this->hr->findJobPosition($command->schoolId, $command->jobPositionId) === null) {
            return ReactivateJobPositionResult::failure(['hr.job_position_not_found']);
        }

        $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');
        $this->unitOfWork->transaction(function () use ($command, $key, $at): void {
            $this->hr->setJobPositionStatus(
                $command->schoolId,
                $command->jobPositionId,
                JobPositionStatus::Active,
                $at,
            );
            $this->outbox->stage(new JobPositionReactivated(
                $command->jobPositionId,
                $command->schoolId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, [
                'job_position_id' => $command->jobPositionId,
            ]);
        });

        return ReactivateJobPositionResult::success($command->jobPositionId);
    }
}
