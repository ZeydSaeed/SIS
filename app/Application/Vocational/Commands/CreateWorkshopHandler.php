<?php

namespace App\Application\Vocational\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Vocational\Results\CreateWorkshopResult;
use App\Domain\Vocational\Events\WorkshopCreated;
use App\Domain\Vocational\Repositories\WorkshopRepositoryInterface;
use App\Domain\Vocational\Support\VocationalIdempotencyGuard;
use App\Domain\Vocational\Support\WorkshopCapacityRules;
use App\Domain\Vocational\ValueObjects\WorkshopStatus;

final class CreateWorkshopHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CreateWorkshop';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly WorkshopRepositoryInterface $workshops,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CreateWorkshopResult
    {
        assert($command instanceof CreateWorkshopCommand);
        $key = VocationalIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return CreateWorkshopResult::fromIdempotency((int) $cached['workshop_id']);
        }

        $code = strtoupper(trim($command->code));
        $name = trim($command->name);
        if ($code === '' || $name === '') {
            return CreateWorkshopResult::failure(['vocational.workshop_identity_invalid']);
        }

        $capacityErrors = WorkshopCapacityRules::validate($command->capacity, $command->safetyCapacity);
        if ($capacityErrors !== []) {
            return CreateWorkshopResult::failure($capacityErrors);
        }

        if ($this->workshops->codeExists($command->schoolId, $code)) {
            return CreateWorkshopResult::failure(['vocational.workshop_code_exists']);
        }

        $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');
        $id = $this->unitOfWork->transaction(function () use ($command, $key, $code, $name, $at): int {
            $id = $this->workshops->create(
                $command->schoolId,
                $code,
                $name,
                $command->capacity,
                $command->safetyCapacity,
                $command->roomId,
                WorkshopStatus::Active,
                $at,
            );
            $this->outbox->stage(new WorkshopCreated(
                $id,
                $command->schoolId,
                $code,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['workshop_id' => $id]);

            return $id;
        });

        return CreateWorkshopResult::success($id);
    }
}
