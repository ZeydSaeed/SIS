<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Timetable\Results\LockSchedulesResult;
use App\Application\Timetable\Support\EngineTransaction;
use App\Domain\Timetable\Repositories\ScheduleRepositoryInterface;

final class LockSchedulesHandler implements CommandHandler
{
    public function __construct(
        private readonly EngineTransaction $tx,
        private readonly ScheduleRepositoryInterface $schedules,
    ) {}

    public function handle(Command $command): LockSchedulesResult
    {
        assert($command instanceof LockSchedulesCommand);
        $ids = array_values(array_unique(array_map('intval', $command->scheduleIds)));
        if ($ids === [] || count($ids) > 500) {
            return LockSchedulesResult::failure('timetable.lock_invalid');
        }

        return $this->tx->run(LockSchedulesResult::class, $command->idempotencyKey, 'LockSchedules', function () use ($command, $ids): LockSchedulesResult {
            $changed = $this->schedules->setLocked($command->schoolId, $ids, $command->lock ? (new \DateTimeImmutable)->format('Y-m-d H:i:sP') : null, $command->userId);
            if ($changed === 0) {
                return LockSchedulesResult::failure('timetable.schedule_not_found');
            }
            $this->tx->stage($command->lock ? 'schedules_locked' : 'schedules_unlocked', $command->schoolId, $command->academicYearId, null, $command->userId, ['schedule_ids' => $ids]);

            return LockSchedulesResult::success(null, ['changed' => $changed]);
        });
    }
}
