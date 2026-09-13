<?php

namespace App\Application\Vocational\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Vocational\Results\ReactivateSpecializationResult;
use App\Domain\Vocational\Events\SpecializationReactivated;
use App\Domain\Vocational\Repositories\VocationalCatalogRepositoryInterface;
use App\Domain\Vocational\Support\VocationalIdempotencyGuard;

final class ReactivateSpecializationHandler implements CommandHandler
{
    private const COMMAND_NAME = 'ReactivateSpecialization';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly VocationalCatalogRepositoryInterface $catalog,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): ReactivateSpecializationResult
    {
        assert($command instanceof ReactivateSpecializationCommand);
        $key = VocationalIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return ReactivateSpecializationResult::fromIdempotency((int) $cached['specialization_id']);
        }

        $at = now()->toIso8601String();
        $this->unitOfWork->transaction(function () use ($command, $key, $at): void {
            $this->catalog->reactivateSpecialization($command->schoolId, $command->specializationId, $at);
            $this->outbox->stage(new SpecializationReactivated(
                $command->specializationId,
                $command->schoolId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, [
                'specialization_id' => $command->specializationId,
            ]);
        });

        return ReactivateSpecializationResult::success($command->specializationId);
    }
}
