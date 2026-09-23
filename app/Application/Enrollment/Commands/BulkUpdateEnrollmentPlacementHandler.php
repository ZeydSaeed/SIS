<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\UnitOfWork;
use App\Application\Enrollment\Results\BulkUpdateEnrollmentPlacementResult;
use App\Application\Enrollment\Services\BulkEnrollmentPlacementExecutor;

final class BulkUpdateEnrollmentPlacementHandler implements CommandHandler
{
    private const COMMAND_NAME = 'BulkUpdateEnrollmentPlacement';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly BulkEnrollmentPlacementExecutor $executor,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): BulkUpdateEnrollmentPlacementResult
    {
        assert($command instanceof BulkUpdateEnrollmentPlacementCommand);

        $enrollmentIds = $this->executor->prepareIds($command);

        if ($command->idempotencyKey !== null) {
            $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
            if ($cached !== null) {
                /** @var list<int> $cachedIds */
                $cachedIds = array_map('intval', $cached['enrollment_ids'] ?? []);

                return BulkUpdateEnrollmentPlacementResult::fromIdempotency(
                    $cachedIds,
                    (int) ($cached['class_id'] ?? 0),
                    (int) ($cached['section_id'] ?? 0),
                );
            }
        }

        $outcome = $this->unitOfWork->transaction(
            fn () => $this->executor->applyAll($command, $enrollmentIds),
        );

        $updatedIds = array_values(array_unique($outcome['updatedIds']));
        $skippedIds = array_values(array_unique($outcome['skippedIds']));

        if ($command->idempotencyKey !== null) {
            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, [
                'enrollment_ids' => $updatedIds,
                'class_id' => $outcome['resolvedClassId'],
                'section_id' => $outcome['resolvedSectionId'],
            ]);
        }

        return BulkUpdateEnrollmentPlacementResult::success(
            $updatedIds,
            $outcome['resolvedClassId'],
            $outcome['resolvedSectionId'],
            $skippedIds,
            $outcome['skipReasons'],
        );
    }
}
