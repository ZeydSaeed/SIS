<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Timetable\Results\SaveTimetableRoomResult;
use App\Application\Timetable\Support\EngineTransaction;
use App\Domain\Timetable\Repositories\TimetablePlaceRepositoryInterface;
use App\Domain\Timetable\Services\TimetablePlaceGuard;

final class SaveTimetableRoomHandler implements CommandHandler
{
    public function __construct(
        private readonly EngineTransaction $tx,
        private readonly TimetablePlaceRepositoryInterface $places,
        private readonly TimetablePlaceGuard $guard,
    ) {}

    public function handle(Command $command): SaveTimetableRoomResult
    {
        assert($command instanceof SaveTimetableRoomCommand);
        $error = $this->guard->roomRejection($command->schoolId, $command->roomId, $command->branchId, $command->code, $command->name, $command->capacity, $command->roomType);
        if ($error !== null) {
            return SaveTimetableRoomResult::failure($error);
        }

        return $this->tx->run(SaveTimetableRoomResult::class, $command->idempotencyKey, 'SaveTimetableRoom', function () use ($command): SaveTimetableRoomResult {
            $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');
            if ($command->roomId === null) {
                $id = $this->places->createRoom($command->branchId, $command->code, $command->name, $command->capacity, $command->roomType, $at);
            } else {
                $id = $command->roomId;
                $this->places->updateRoom($id, $command->name, $command->capacity, $command->roomType, $at);
            }

            return SaveTimetableRoomResult::success($id);
        });
    }
}
