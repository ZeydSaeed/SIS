<?php

namespace App\Application\Academic\Commands;

use App\Application\Academic\Results\ChangeHolidayStatusResult;
use App\Application\Academic\Support\HolidayTransaction;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Domain\Academic\Repositories\HolidayRepositoryInterface;
use App\Domain\Academic\Services\HolidayGuard;

final class ChangeHolidayStatusHandler implements CommandHandler
{
    public const COMMAND_NAME = 'Academic.ChangeHolidayStatus';

    public function __construct(
        private readonly HolidayTransaction $tx,
        private readonly HolidayRepositoryInterface $holidays,
        private readonly HolidayGuard $guard,
    ) {}

    public function handle(Command $command): ChangeHolidayStatusResult
    {
        assert($command instanceof ChangeHolidayStatusCommand);
        $replayed = $this->tx->replayed($command->idempotencyKey, self::COMMAND_NAME);
        if ($replayed !== null) {
            return ChangeHolidayStatusResult::fromIdempotency($replayed);
        }

        $error = $this->guard->statusRejection($command->schoolId, $command->holidayId, $command->active);
        if ($error !== null) {
            return ChangeHolidayStatusResult::failure($error);
        }

        return $this->tx->run(
            ChangeHolidayStatusResult::class,
            $command->idempotencyKey,
            self::COMMAND_NAME,
            $command->schoolId,
            $command->active ? 'reactivated' : 'deactivated',
            function (string $at) use ($command): int {
                $this->holidays->setStatus(
                    $command->holidayId,
                    $command->active ? HolidayRepositoryInterface::ACTIVE : HolidayRepositoryInterface::INACTIVE,
                    $at,
                );

                return $command->holidayId;
            },
        );
    }
}
