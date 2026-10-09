<?php

namespace App\Application\Organization\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Organization\Results\SaveRoomResult;
use App\Application\Organization\Support\RoomCatalogueTransaction;
use App\Domain\Organization\Repositories\RoomCatalogueRepositoryInterface;
use App\Domain\Organization\Services\RoomCatalogueGuard;

final class SaveRoomHandler implements CommandHandler
{
    private const COMMAND_NAME = 'SaveRoom';

    public function __construct(
        private readonly RoomCatalogueTransaction $tx,
        private readonly RoomCatalogueRepositoryInterface $rooms,
        private readonly RoomCatalogueGuard $guard,
    ) {}

    public function handle(Command $command): SaveRoomResult
    {
        assert($command instanceof SaveRoomCommand);
        $replayed = $this->tx->replayed($command->idempotencyKey, self::COMMAND_NAME);
        if ($replayed !== null) {
            return SaveRoomResult::fromIdempotency($replayed);
        }

        $error = $this->guard->roomRejection($command->schoolId, $command->roomId, $command->branchId, $command->code, $command->details);
        if ($error !== null) {
            return SaveRoomResult::failure($error);
        }

        return $this->tx->run(
            SaveRoomResult::class,
            $command->idempotencyKey,
            self::COMMAND_NAME,
            'room',
            $command->schoolId,
            $command->roomId === null ? 'created' : 'updated',
            function (string $at) use ($command): int {
                if ($command->roomId === null) {
                    return $this->rooms->createRoom((int) $command->branchId, (string) $command->code, $command->details, $at);
                }
                $this->rooms->updateRoom($command->roomId, $command->details, $at);

                return $command->roomId;
            },
        );
    }
}
