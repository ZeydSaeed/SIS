<?php

namespace App\Application\Organization\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Organization\Results\ChangeRoomTypeStatusResult;
use App\Application\Organization\Support\RoomCatalogueTransaction;
use App\Domain\Organization\Repositories\RoomCatalogueRepositoryInterface;
use App\Domain\Organization\Services\RoomCatalogueGuard;

final class ChangeRoomTypeStatusHandler implements CommandHandler
{
    private const COMMAND_NAME = 'ChangeRoomTypeStatus';

    public function __construct(
        private readonly RoomCatalogueTransaction $tx,
        private readonly RoomCatalogueRepositoryInterface $rooms,
        private readonly RoomCatalogueGuard $guard,
    ) {}

    public function handle(Command $command): ChangeRoomTypeStatusResult
    {
        assert($command instanceof ChangeRoomTypeStatusCommand);
        $replayed = $this->tx->replayed($command->idempotencyKey, self::COMMAND_NAME);
        if ($replayed !== null) {
            return ChangeRoomTypeStatusResult::fromIdempotency($replayed);
        }

        $error = $this->guard->typeStatusRejection($command->schoolId, $command->typeId, $command->active);
        if ($error !== null) {
            return ChangeRoomTypeStatusResult::failure($error);
        }

        return $this->tx->run(
            ChangeRoomTypeStatusResult::class,
            $command->idempotencyKey,
            self::COMMAND_NAME,
            'room_type',
            $command->schoolId,
            $command->active ? 'reactivated' : 'deactivated',
            function (string $at) use ($command): int {
                $this->rooms->setTypeStatus(
                    $command->typeId,
                    $command->active ? RoomCatalogueRepositoryInterface::ACTIVE : RoomCatalogueRepositoryInterface::INACTIVE,
                    $at,
                );

                return $command->typeId;
            },
        );
    }
}
