<?php

namespace App\Application\Hr\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Hr\Results\CreateJobPositionResult;
use App\Domain\Hr\Events\JobPositionCreated;
use App\Domain\Hr\Repositories\HrRepositoryInterface;
use App\Domain\Hr\Support\HrIdempotencyGuard;
use App\Domain\Hr\ValueObjects\JobPositionCategory;
use App\Domain\Hr\ValueObjects\JobPositionStatus;

final class CreateJobPositionHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CreateJobPosition';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly HrRepositoryInterface $hr,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CreateJobPositionResult
    {
        assert($command instanceof CreateJobPositionCommand);
        $key = HrIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return CreateJobPositionResult::fromIdempotency((int) $cached['job_position_id']);
        }

        $code = strtoupper(trim($command->code));
        $name = trim($command->name);
        if ($code === '' || $name === '') {
            return CreateJobPositionResult::failure(['hr.job_position_identity_invalid']);
        }
        if (! JobPositionCategory::isValid($command->category)) {
            return CreateJobPositionResult::failure(['hr.job_position_category_invalid']);
        }
        if ($this->hr->jobPositionCodeExists($command->schoolId, $code)) {
            return CreateJobPositionResult::failure(['hr.job_position_code_exists']);
        }

        $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');
        $id = $this->unitOfWork->transaction(function () use ($command, $key, $code, $name, $at): int {
            $id = $this->hr->createJobPosition(
                $command->schoolId,
                $code,
                $name,
                $command->category,
                JobPositionStatus::Active,
                $at,
            );
            $this->outbox->stage(new JobPositionCreated(
                $id,
                $command->schoolId,
                $code,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['job_position_id' => $id]);

            return $id;
        });

        return CreateJobPositionResult::success($id);
    }
}
