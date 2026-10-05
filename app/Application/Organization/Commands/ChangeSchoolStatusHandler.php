<?php

namespace App\Application\Organization\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Organization\Results\ChangeSchoolStatusResult;
use App\Domain\Organization\Events\SchoolUpdated;
use App\Domain\Organization\Repositories\SchoolRepositoryInterface;
use App\Domain\Organization\Services\SchoolRegistryGuard;
use App\Domain\Organization\Support\OrganizationIdempotencyGuard;

final class ChangeSchoolStatusHandler implements CommandHandler
{
    private const COMMAND_NAME = 'ChangeSchoolStatus';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly SchoolRepositoryInterface $schools,
        private readonly SchoolRegistryGuard $guard,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): ChangeSchoolStatusResult
    {
        assert($command instanceof ChangeSchoolStatusCommand);
        $key = OrganizationIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return ChangeSchoolStatusResult::fromIdempotency((int) $cached['school_id']);
        }

        $error = $this->guard->statusRejectionCode($command->schoolId, $command->status);
        if ($error !== null) {
            return ChangeSchoolStatusResult::failure([$error]);
        }

        $this->unitOfWork->transaction(function () use ($command, $key): void {
            $now = new \DateTimeImmutable;
            $this->schools->setStatus(
                $command->schoolId,
                $command->status,
                $now->format(\DateTimeInterface::ATOM),
            );
            $this->outbox->stage(new SchoolUpdated($command->schoolId, $now));
            $this->idempotency->store($key, self::COMMAND_NAME, ['school_id' => $command->schoolId]);
        });

        return ChangeSchoolStatusResult::success($command->schoolId);
    }
}
