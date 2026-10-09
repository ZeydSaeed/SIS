<?php

namespace App\Application\Organization\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Organization\Results\ChangeRoomStatusResult;
use App\Application\Organization\Support\RoomCatalogueTransaction;
use App\Domain\Organization\Repositories\RoomCatalogueRepositoryInterface;
use App\Domain\Organization\Services\RoomCatalogueGuard;

final class ChangeRoomStatusHandler implements CommandHandler
{
    private const COMMAND_NAME = 'ChangeRoomStatus';

    public function __construct(
        private readonly RoomCatalogueTransaction $tx,
        private readonly RoomCatalogueRepositoryInterface $rooms,
        private readonly RoomCatalogueGuard $guard,
    ) {}

    public function handle(Command $command): ChangeRoomStatusResult
    {
        assert($command instanceof ChangeRoomStatusCommand);
        $replayed = $this->tx->replayed($command->idempotencyKey, self::COMMAND_NAME);
        if ($replayed !== null) {
            return ChangeRoomStatusResult::fromIdempotency($replayed);
        }

        $error = $this->guard->roomStatusRejection($command->schoolId, $command->roomId, $command->active);
        if ($error !== null) {
            return ChangeRoomStatusResult::failure($error);
        }

        return $this->tx->run(
            ChangeRoomStatusResult::class,
            $command->idempotencyKey,
            self::COMMAND_NAME,
            'room',
            $command->schoolId,
            $command->active ? 'reactivated' : 'deactivated',
            function (string $at) use ($command): int {
                $this->rooms->setRoomStatus(
                    $command->roomId,
                    $command->active ? RoomCatalogueRepositoryInterface::ACTIVE : RoomCatalogueRepositoryInterface::INACTIVE,
                    $at,
                );

                return $command->roomId;
            },
        );
    }
}
