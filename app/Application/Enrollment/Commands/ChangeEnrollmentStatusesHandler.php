<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\UnitOfWork;
use App\Application\Enrollment\Results\ChangeEnrollmentStatusesResult;
use App\Application\Enrollment\Services\ChangeEnrollmentStatusesExecutor;
use App\Domain\Enrollment\Services\BulkEnrollmentStatusGuard;

final class ChangeEnrollmentStatusesHandler implements CommandHandler
{
    private const COMMAND_NAME = 'ChangeEnrollmentStatuses';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly BulkEnrollmentStatusGuard $guard,
        private readonly ChangeEnrollmentStatusesExecutor $executor,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): ChangeEnrollmentStatusesResult
    {
        assert($command instanceof ChangeEnrollmentStatusesCommand);

        $enrollmentIds = $this->guard->uniqueIds($command->enrollmentIds);
        $this->guard->assertTargetStatus($command->status);

        $cached = $this->cachedResult($command);
        if ($cached !== null) {
            return $cached;
        }

        $this->unitOfWork->transaction(function () use ($command, $enrollmentIds): void {
            $this->executor->applyAll($command, $enrollmentIds);
        });

        if ($command->idempotencyKey !== null) {
            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, [
                'enrollment_ids' => $enrollmentIds,
                'status' => $command->status,
            ]);
        }

        return ChangeEnrollmentStatusesResult::success($enrollmentIds, $command->status);
    }

    private function cachedResult(ChangeEnrollmentStatusesCommand $command): ?ChangeEnrollmentStatusesResult
    {
        if ($command->idempotencyKey === null) {
            return null;
        }

        $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
        if ($cached === null) {
            return null;
        }

        /** @var list<int> $cachedIds */
        $cachedIds = array_map('intval', $cached['enrollment_ids'] ?? []);

        return ChangeEnrollmentStatusesResult::fromIdempotency(
            $cachedIds,
            (int) $cached['status'],
        );
    }
}
