<?php

namespace App\Application\Vocational\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Vocational\Results\CreateWorkshopEquipmentResult;
use App\Domain\Vocational\Events\WorkshopEquipmentCreated;
use App\Domain\Vocational\Repositories\WorkshopEquipmentRepositoryInterface;
use App\Domain\Vocational\Support\VocationalIdempotencyGuard;
use App\Domain\Vocational\ValueObjects\WorkshopEquipmentStatus;

final class CreateWorkshopEquipmentHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CreateWorkshopEquipment';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly WorkshopEquipmentRepositoryInterface $equipment,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CreateWorkshopEquipmentResult
    {
        assert($command instanceof CreateWorkshopEquipmentCommand);
        $key = VocationalIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return CreateWorkshopEquipmentResult::fromIdempotency((int) $cached['equipment_id']);
        }

        $code = strtoupper(trim($command->code));
        $name = trim($command->name);
        if ($code === '' || $name === '') {
            return CreateWorkshopEquipmentResult::failure(['vocational.equipment_identity_invalid']);
        }
        if ($command->quantity < 1) {
            return CreateWorkshopEquipmentResult::failure(['vocational.equipment_quantity_invalid']);
        }
        if (! $this->equipment->workshopBelongsToSchool($command->schoolId, $command->workshopId)) {
            return CreateWorkshopEquipmentResult::failure(['vocational.workshop_not_found']);
        }
        if ($this->equipment->codeExists($command->schoolId, $command->workshopId, $code)) {
            return CreateWorkshopEquipmentResult::failure(['vocational.equipment_code_exists']);
        }

        $id = $this->unitOfWork->transaction(function () use ($command, $key, $code, $name): int {
            $id = $this->equipment->create(
                $command->schoolId,
                $command->workshopId,
                $code,
                $name,
                $command->quantity,
                WorkshopEquipmentStatus::Active,
                (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
            );
            $this->outbox->stage(new WorkshopEquipmentCreated(
                $id,
                $command->schoolId,
                $command->workshopId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['equipment_id' => $id]);

            return $id;
        });

        return CreateWorkshopEquipmentResult::success($id);
    }
}
