<?php

namespace App\Application\Organization\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Organization\Results\CreateDirectorateResult;
use App\Application\Organization\Support\PlaceSchoolsInDirectorate;
use App\Domain\Organization\Events\DirectorateCreated;
use App\Domain\Organization\Repositories\DirectorateRepositoryInterface;
use App\Domain\Organization\Services\DirectorateRegistryGuard;
use App\Domain\Organization\Support\OrganizationIdempotencyGuard;

final class CreateDirectorateHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CreateDirectorate';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly DirectorateRepositoryInterface $directorates,
        private readonly DirectorateRegistryGuard $guard,
        private readonly PlaceSchoolsInDirectorate $placement,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CreateDirectorateResult
    {
        assert($command instanceof CreateDirectorateCommand);
        $key = OrganizationIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return CreateDirectorateResult::fromIdempotency((int) $cached['directorate_id']);
        }

        $ministryId = $this->directorates->defaultMinistryId();
        $error = $this->guard->createRejectionCode(
            $command->name,
            $ministryId,
            $command->schoolIds,
            $command->allowedSchoolIds,
        );
        if ($error !== null || $ministryId === null) {
            return CreateDirectorateResult::failure([$error ?? 'organization.ministry_missing']);
        }

        $id = $this->unitOfWork->transaction(function () use ($command, $key, $ministryId): int {
            $now = new \DateTimeImmutable;
            $id = $this->directorates->create(
                $ministryId,
                $this->directorates->nextCode(),
                trim($command->name),
                $command->region,
                $now->format(\DateTimeInterface::ATOM),
            );
            $this->placement->place($id, $command->schoolIds, $now);
            $this->outbox->stage(new DirectorateCreated($id, $now));
            $this->idempotency->store($key, self::COMMAND_NAME, ['directorate_id' => $id]);

            return $id;
        });

        return CreateDirectorateResult::success($id);
    }
}
