<?php

namespace App\Application\Academic\Commands;

use App\Application\Academic\Results\SaveHolidayResult;
use App\Application\Academic\Support\HolidayTransaction;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Domain\Academic\Repositories\HolidayRepositoryInterface;
use App\Domain\Academic\Services\HolidayGuard;

final class SaveHolidayHandler implements CommandHandler
{
    public const COMMAND_NAME = 'Academic.SaveHoliday';

    public function __construct(
        private readonly HolidayTransaction $tx,
        private readonly HolidayRepositoryInterface $holidays,
        private readonly HolidayGuard $guard,
    ) {}

    public function handle(Command $command): SaveHolidayResult
    {
        assert($command instanceof SaveHolidayCommand);
        $replayed = $this->tx->replayed($command->idempotencyKey, self::COMMAND_NAME);
        if ($replayed !== null) {
            return SaveHolidayResult::fromIdempotency($replayed);
        }

        $name = trim($command->name);
        $error = $this->guard->saveRejection(
            $command->schoolId,
            $command->holidayId,
            $command->academicYearId,
            $name,
            $command->startDate,
            $command->endDate,
            $command->holidayType,
        );
        if ($error !== null) {
            return SaveHolidayResult::failure($error);
        }

        return $this->tx->run(
            SaveHolidayResult::class,
            $command->idempotencyKey,
            self::COMMAND_NAME,
            $command->schoolId,
            $command->holidayId === null ? 'created' : 'updated',
            function (string $at) use ($command, $name): int {
                if ($command->holidayId === null) {
                    return $this->holidays->create(
                        $command->schoolId,
                        (int) $command->academicYearId,
                        $name,
                        $command->startDate,
                        $command->endDate,
                        $command->holidayType,
                        $at,
                    );
                }
                $this->holidays->update($command->holidayId, $name, $command->startDate, $command->endDate, $command->holidayType, $at);

                return $command->holidayId;
            },
        );
    }
}
