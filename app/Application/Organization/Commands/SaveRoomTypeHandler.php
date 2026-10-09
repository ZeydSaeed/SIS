<?php

namespace App\Application\Organization\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Organization\Results\SaveRoomTypeResult;
use App\Application\Organization\Support\RoomCatalogueTransaction;
use App\Domain\Organization\Repositories\RoomCatalogueRepositoryInterface;
use App\Domain\Organization\Services\RoomCatalogueGuard;
use App\Domain\Organization\ValueObjects\RoomKind;
use App\Domain\Shared\ValueObjects\DisplayAppearance;

final class SaveRoomTypeHandler implements CommandHandler
{
    private const COMMAND_NAME = 'SaveRoomType';

    public function __construct(
        private readonly RoomCatalogueTransaction $tx,
        private readonly RoomCatalogueRepositoryInterface $rooms,
        private readonly RoomCatalogueGuard $guard,
    ) {}

    public function handle(Command $command): SaveRoomTypeResult
    {
        assert($command instanceof SaveRoomTypeCommand);
        $replayed = $this->tx->replayed($command->idempotencyKey, self::COMMAND_NAME);
        if ($replayed !== null) {
            return SaveRoomTypeResult::fromIdempotency($replayed);
        }

        $appearance = DisplayAppearance::of($command->abbreviation, $command->colorHue);
        $error = $this->guard->typeRejection($command->schoolId, $command->typeId, $command->code, $command->name, $appearance, $command->kind);
        if ($error !== null) {
            return SaveRoomTypeResult::failure($error);
        }
        $practical = $command->supportsPractical ?? RoomKind::from($command->kind)->practicalByDefault();

        return $this->tx->run(
            SaveRoomTypeResult::class,
            $command->idempotencyKey,
            self::COMMAND_NAME,
            'room_type',
            $command->schoolId,
            $command->typeId === null ? 'created' : 'updated',
            function (string $at) use ($command, $appearance, $practical): int {
                if ($command->typeId === null) {
                    return $this->rooms->createType($command->schoolId, (string) $command->code, $command->name, $appearance, $command->kind, $practical, $at);
                }
                $this->rooms->updateType($command->typeId, $command->name, $appearance, $command->kind, $practical, $at);

                return $command->typeId;
            },
        );
    }
}
