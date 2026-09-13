<?php

namespace App\Application\Vocational\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Vocational\Results\DeactivateWorkshopEquipmentResult;
use App\Domain\Vocational\Events\WorkshopEquipmentDeactivated;
use App\Domain\Vocational\Repositories\WorkshopEquipmentRepositoryInterface;
use App\Domain\Vocational\Support\VocationalIdempotencyGuard;
use App\Domain\Vocational\ValueObjects\WorkshopEquipmentStatus;

final class DeactivateWorkshopEquipmentHandler implements CommandHandler
{
    private const COMMAND_NAME = 'DeactivateWorkshopEquipment';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly WorkshopEquipmentRepositoryInterface $equipment,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): DeactivateWorkshopEquipmentResult
    {
        assert($command instanceof DeactivateWorkshopEquipmentCommand);
        $key = VocationalIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return DeactivateWorkshopEquipmentResult::fromIdempotency((int) $cached['equipment_id']);
        }

        $row = $this->equipment->find($command->schoolId, $command->equipmentId);
        if ($row === null) {
            return DeactivateWorkshopEquipmentResult::failure(['vocational.equipment_not_found']);
        }
        if ($row->status === WorkshopEquipmentStatus::Inactive) {
            return DeactivateWorkshopEquipmentResult::failure(['vocational.equipment_already_inactive']);
        }

        $this->unitOfWork->transaction(function () use ($command, $key): void {
            $this->equipment->setStatus(
                $command->schoolId,
                $command->equipmentId,
                WorkshopEquipmentStatus::Inactive,
                (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
            );
            $this->outbox->stage(new WorkshopEquipmentDeactivated(
                $command->equipmentId,
                $command->schoolId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['equipment_id' => $command->equipmentId]);
        });

        return DeactivateWorkshopEquipmentResult::success($command->equipmentId);
    }
}
