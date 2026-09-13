<?php

namespace App\Application\Vocational\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Vocational\Results\DeactivateWorkshopResult;
use App\Domain\Vocational\Events\WorkshopDeactivated;
use App\Domain\Vocational\Repositories\WorkshopRepositoryInterface;
use App\Domain\Vocational\Support\VocationalIdempotencyGuard;
use App\Domain\Vocational\ValueObjects\WorkshopStatus;

final class DeactivateWorkshopHandler implements CommandHandler
{
    private const COMMAND_NAME = 'DeactivateWorkshop';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly WorkshopRepositoryInterface $workshops,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): DeactivateWorkshopResult
    {
        assert($command instanceof DeactivateWorkshopCommand);
        $key = VocationalIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return DeactivateWorkshopResult::fromIdempotency((int) $cached['workshop_id']);
        }

        $row = $this->workshops->find($command->schoolId, $command->workshopId);
        if ($row === null) {
            return DeactivateWorkshopResult::failure(['vocational.workshop_not_found']);
        }
        if ($row->status === WorkshopStatus::Inactive) {
            return DeactivateWorkshopResult::failure(['vocational.workshop_already_inactive']);
        }

        $this->unitOfWork->transaction(function () use ($command, $key): void {
            $this->workshops->setStatus(
                $command->schoolId,
                $command->workshopId,
                WorkshopStatus::Inactive,
                (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
            );
            $this->outbox->stage(new WorkshopDeactivated(
                $command->workshopId,
                $command->schoolId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['workshop_id' => $command->workshopId]);
        });

        return DeactivateWorkshopResult::success($command->workshopId);
    }
}
