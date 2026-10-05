<?php

namespace App\Application\Organization\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Organization\Results\UpdateSchoolResult;
use App\Domain\Organization\Events\SchoolUpdated;
use App\Domain\Organization\Repositories\SchoolRepositoryInterface;
use App\Domain\Organization\Services\SchoolRegistryGuard;
use App\Domain\Organization\Support\OrganizationIdempotencyGuard;

final class UpdateSchoolHandler implements CommandHandler
{
    private const COMMAND_NAME = 'UpdateSchool';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly SchoolRepositoryInterface $schools,
        private readonly SchoolRegistryGuard $guard,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): UpdateSchoolResult
    {
        assert($command instanceof UpdateSchoolCommand);
        $key = OrganizationIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return UpdateSchoolResult::fromIdempotency((int) $cached['school_id']);
        }

        $error = $this->guard->updateRejectionCode($command->schoolId, $command->fields);
        if ($error !== null) {
            return UpdateSchoolResult::failure([$error]);
        }

        $this->unitOfWork->transaction(function () use ($command, $key): void {
            $now = new \DateTimeImmutable;
            $this->schools->update(
                $command->schoolId,
                $command->fields,
                $now->format(\DateTimeInterface::ATOM),
            );
            $this->outbox->stage(new SchoolUpdated($command->schoolId, $now));
            $this->idempotency->store($key, self::COMMAND_NAME, ['school_id' => $command->schoolId]);
        });

        return UpdateSchoolResult::success($command->schoolId);
    }
}
