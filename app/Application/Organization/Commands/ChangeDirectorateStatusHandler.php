<?php

namespace App\Application\Organization\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Organization\Results\ChangeDirectorateStatusResult;
use App\Domain\Organization\Events\DirectorateUpdated;
use App\Domain\Organization\Repositories\DirectorateRepositoryInterface;
use App\Domain\Organization\Services\DirectorateRegistryGuard;
use App\Domain\Organization\Support\OrganizationIdempotencyGuard;

final class ChangeDirectorateStatusHandler implements CommandHandler
{
    private const COMMAND_NAME = 'ChangeDirectorateStatus';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly DirectorateRepositoryInterface $directorates,
        private readonly DirectorateRegistryGuard $guard,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): ChangeDirectorateStatusResult
    {
        assert($command instanceof ChangeDirectorateStatusCommand);
        $key = OrganizationIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return ChangeDirectorateStatusResult::fromIdempotency((int) $cached['directorate_id']);
        }

        $error = $this->guard->statusRejectionCode($command->directorateId, $command->status);
        if ($error !== null) {
            return ChangeDirectorateStatusResult::failure([$error]);
        }

        $this->unitOfWork->transaction(function () use ($command, $key): void {
            $now = new \DateTimeImmutable;
            $this->directorates->setStatus(
                $command->directorateId,
                $command->status,
                $now->format(\DateTimeInterface::ATOM),
            );
            $this->outbox->stage(new DirectorateUpdated($command->directorateId, $now));
            $this->idempotency->store($key, self::COMMAND_NAME, ['directorate_id' => $command->directorateId]);
        });

        return ChangeDirectorateStatusResult::success($command->directorateId);
    }
}
