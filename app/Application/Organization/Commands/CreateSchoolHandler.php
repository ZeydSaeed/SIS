<?php

namespace App\Application\Organization\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Organization\Contracts\SchoolMembershipPort;
use App\Application\Organization\Results\CreateSchoolResult;
use App\Domain\Organization\Events\SchoolCreated;
use App\Domain\Organization\Repositories\SchoolRepositoryInterface;
use App\Domain\Organization\Services\SchoolRegistryGuard;
use App\Domain\Organization\Support\OrganizationIdempotencyGuard;
use App\Domain\Organization\ValueObjects\SchoolType;

final class CreateSchoolHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CreateSchool';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly SchoolRepositoryInterface $schools,
        private readonly SchoolRegistryGuard $guard,
        private readonly SchoolMembershipPort $membership,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CreateSchoolResult
    {
        assert($command instanceof CreateSchoolCommand);
        $key = OrganizationIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return CreateSchoolResult::fromIdempotency((int) $cached['school_id']);
        }

        $error = $this->guard->createRejectionCode($command->name, $command->directorateId);
        if ($error !== null) {
            return CreateSchoolResult::failure([$error]);
        }

        $id = $this->unitOfWork->transaction(function () use ($command, $key): int {
            $now = new \DateTimeImmutable;
            $id = $this->schools->create(
                $command->directorateId,
                $this->schools->nextCode(),
                trim($command->name),
                SchoolType::Vocational->value,
                $command->address,
                $command->phone,
                $command->email,
                $now->format(\DateTimeInterface::ATOM),
            );

            // Creator keeps working in the new school with the same roles they hold in the current one.
            $this->membership->grantSameRoles(
                $command->createdByUserId,
                $command->sourceSchoolId,
                $id,
                $now->format('Y-m-d'),
            );

            $this->outbox->stage(new SchoolCreated($id, $command->createdByUserId, $now));
            $this->idempotency->store($key, self::COMMAND_NAME, ['school_id' => $id]);

            return $id;
        });

        return CreateSchoolResult::success($id);
    }
}
