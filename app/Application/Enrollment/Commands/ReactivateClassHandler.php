<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Enrollment\Results\ReactivateClassResult;
use App\Domain\Enrollment\Events\ClassReactivated;
use App\Domain\Enrollment\Repositories\EnrollmentStructureRepositoryInterface;
use App\Domain\Enrollment\Support\EnrollmentIdempotencyGuard;

final class ReactivateClassHandler implements CommandHandler
{
    private const COMMAND_NAME = 'ReactivateClass';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly EnrollmentStructureRepositoryInterface $structure,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): ReactivateClassResult
    {
        assert($command instanceof ReactivateClassCommand);
        $key = EnrollmentIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return ReactivateClassResult::fromIdempotency((int) $cached['class_id']);
        }

        $at = now()->toIso8601String();
        $this->unitOfWork->transaction(function () use ($command, $key, $at): void {
            $this->structure->reactivateClass($command->schoolId, $command->classId, $at);
            $this->outbox->stage(new ClassReactivated(
                $command->classId,
                $command->schoolId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['class_id' => $command->classId]);
        });

        return ReactivateClassResult::success($command->classId);
    }
}
