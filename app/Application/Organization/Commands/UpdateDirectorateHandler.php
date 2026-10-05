<?php

namespace App\Application\Organization\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Organization\Results\UpdateDirectorateResult;
use App\Application\Organization\Support\PlaceSchoolsInDirectorate;
use App\Domain\Organization\Events\DirectorateUpdated;
use App\Domain\Organization\Repositories\DirectorateRepositoryInterface;
use App\Domain\Organization\Services\DirectorateRegistryGuard;
use App\Domain\Organization\Support\OrganizationIdempotencyGuard;

final class UpdateDirectorateHandler implements CommandHandler
{
    private const COMMAND_NAME = 'UpdateDirectorate';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly DirectorateRepositoryInterface $directorates,
        private readonly DirectorateRegistryGuard $guard,
        private readonly PlaceSchoolsInDirectorate $placement,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): UpdateDirectorateResult
    {
        assert($command instanceof UpdateDirectorateCommand);
        $key = OrganizationIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return UpdateDirectorateResult::fromIdempotency((int) $cached['directorate_id']);
        }

        $error = $this->guard->updateRejectionCode(
            $command->directorateId,
            $command->fields,
            $command->schoolIds,
            $command->allowedSchoolIds,
        );
        if ($error !== null) {
            return UpdateDirectorateResult::failure([$error]);
        }

        $this->unitOfWork->transaction(function () use ($command, $key): void {
            $now = new \DateTimeImmutable;
            if ($command->fields !== []) {
                $this->directorates->update(
                    $command->directorateId,
                    $command->fields,
                    $now->format(\DateTimeInterface::ATOM),
                );
            }
            if ($command->schoolIds !== null) {
                $this->placement->place($command->directorateId, $command->schoolIds, $now);
            }
            $this->outbox->stage(new DirectorateUpdated($command->directorateId, $now));
            $this->idempotency->store($key, self::COMMAND_NAME, ['directorate_id' => $command->directorateId]);
        });

        return UpdateDirectorateResult::success($command->directorateId);
    }
}
