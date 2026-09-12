<?php

namespace App\Application\Vocational\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Vocational\Results\CreateSpecializationResult;
use App\Domain\Vocational\Events\SpecializationCreated;
use App\Domain\Vocational\Repositories\VocationalCatalogRepositoryInterface;
use App\Domain\Vocational\Support\VocationalIdempotencyGuard;

final class CreateSpecializationHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CreateSpecialization';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly VocationalCatalogRepositoryInterface $catalog,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CreateSpecializationResult
    {
        assert($command instanceof CreateSpecializationCommand);
        $key = VocationalIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return CreateSpecializationResult::fromIdempotency((int) $cached['specialization_id']);
        }

        $at = now()->toIso8601String();
        $id = $this->unitOfWork->transaction(function () use ($command, $key, $at): int {
            $id = $this->catalog->createSpecialization(
                $command->schoolId,
                $command->code,
                $command->name,
                $command->description,
                $at,
            );
            $this->outbox->stage(new SpecializationCreated(
                $id,
                $command->schoolId,
                $command->code,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['specialization_id' => $id]);

            return $id;
        });

        return CreateSpecializationResult::success($id);
    }
}
