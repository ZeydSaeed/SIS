<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Timetable\Results\SaveTimetableWorkshopResult;
use App\Application\Timetable\Support\EngineTransaction;
use App\Domain\Timetable\Repositories\TimetablePlaceRepositoryInterface;
use App\Domain\Timetable\Services\TimetablePlaceGuard;

final class SaveTimetableWorkshopHandler implements CommandHandler
{
    public function __construct(
        private readonly EngineTransaction $tx,
        private readonly TimetablePlaceRepositoryInterface $places,
        private readonly TimetablePlaceGuard $guard,
    ) {}

    public function handle(Command $command): SaveTimetableWorkshopResult
    {
        assert($command instanceof SaveTimetableWorkshopCommand);
        $error = $this->guard->workshopRejection($command->schoolId, $command->workshopId, $command->code, $command->name, $command->capacity, $command->safetyCapacity, $command->roomId);
        if ($error !== null) {
            return SaveTimetableWorkshopResult::failure($error);
        }

        return $this->tx->run(SaveTimetableWorkshopResult::class, $command->idempotencyKey, 'SaveTimetableWorkshop', function () use ($command): SaveTimetableWorkshopResult {
            $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');
            if ($command->workshopId === null) {
                $id = $this->places->createWorkshop($command->schoolId, $command->code, $command->name, $command->capacity, $command->safetyCapacity, $command->roomId, $at);
            } else {
                $id = $command->workshopId;
                $this->places->updateWorkshop($id, $command->name, $command->capacity, $command->safetyCapacity, $command->roomId, $at);
            }

            return SaveTimetableWorkshopResult::success($id);
        });
    }
}
