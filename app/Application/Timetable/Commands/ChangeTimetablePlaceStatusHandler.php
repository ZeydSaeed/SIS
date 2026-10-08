<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Timetable\Results\ChangeTimetablePlaceStatusResult;
use App\Application\Timetable\Support\EngineTransaction;
use App\Domain\Timetable\Repositories\TimetablePlaceRepositoryInterface;

/** Out of service is refused while a lesson, an activity or a workshop still points at the place. */
final class ChangeTimetablePlaceStatusHandler implements CommandHandler
{
    public function __construct(
        private readonly EngineTransaction $tx,
        private readonly TimetablePlaceRepositoryInterface $places,
    ) {}

    public function handle(Command $command): ChangeTimetablePlaceStatusResult
    {
        assert($command instanceof ChangeTimetablePlaceStatusCommand);
        $isRoom = $command->kind === 'room';
        if (! in_array($command->status, [1, 2], true) || ! in_array($command->kind, ['room', 'workshop'], true)) {
            return ChangeTimetablePlaceStatusResult::failure('timetable.place_status_invalid');
        }
        $place = $isRoom ? $this->places->findRoom($command->schoolId, $command->placeId) : $this->places->findWorkshop($command->schoolId, $command->placeId);
        if ($place === null) {
            return ChangeTimetablePlaceStatusResult::failure($isRoom ? 'timetable.room_not_found' : 'timetable.workshop_not_found');
        }
        $used = $command->status === 2
            ? ($isRoom ? $this->places->roomUsage($command->schoolId, $command->placeId) : $this->places->workshopUsage($command->schoolId, $command->placeId))
            : 0;
        if ($used > 0) {
            return ChangeTimetablePlaceStatusResult::failure('timetable.place_in_use');
        }

        return $this->tx->run(ChangeTimetablePlaceStatusResult::class, $command->idempotencyKey, 'ChangeTimetablePlaceStatus', function () use ($command, $isRoom): ChangeTimetablePlaceStatusResult {
            $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');
            $isRoom
                ? $this->places->setRoomStatus($command->placeId, $command->status, $at)
                : $this->places->setWorkshopStatus($command->placeId, $command->status, $at);

            return ChangeTimetablePlaceStatusResult::success($command->placeId);
        });
    }
}
