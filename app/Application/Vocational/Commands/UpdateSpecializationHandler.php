<?php

namespace App\Application\Vocational\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Vocational\Results\UpdateSpecializationResult;
use App\Domain\Vocational\Events\SpecializationUpdated;
use App\Domain\Vocational\Repositories\VocationalCatalogRepositoryInterface;
use App\Domain\Vocational\Support\VocationalIdempotencyGuard;

final class UpdateSpecializationHandler implements CommandHandler
{
    private const COMMAND_NAME = 'UpdateSpecialization';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly VocationalCatalogRepositoryInterface $catalog,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): UpdateSpecializationResult
    {
        assert($command instanceof UpdateSpecializationCommand);
        $key = VocationalIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return UpdateSpecializationResult::fromIdempotency((int) $cached['specialization_id']);
        }

        $at = now()->toIso8601String();
        $this->unitOfWork->transaction(function () use ($command, $key, $at): void {
            $this->catalog->updateSpecialization(
                $command->schoolId,
                $command->specializationId,
                $command->code,
                $command->name,
                $command->description,
                $at,
            );
            $this->outbox->stage(new SpecializationUpdated(
                $command->specializationId,
                $command->schoolId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, [
                'specialization_id' => $command->specializationId,
            ]);
        });

        return UpdateSpecializationResult::success($command->specializationId);
    }
}
