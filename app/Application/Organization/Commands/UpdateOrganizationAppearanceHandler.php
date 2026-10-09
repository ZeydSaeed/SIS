<?php

namespace App\Application\Organization\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Organization\Results\UpdateOrganizationAppearanceResult;
use App\Application\Organization\Support\RoomCatalogueTransaction;
use App\Domain\Organization\Repositories\OrganizationAppearanceRepositoryInterface;
use App\Domain\Shared\ValueObjects\DisplayAppearance;

final class UpdateOrganizationAppearanceHandler implements CommandHandler
{
    private const COMMAND_NAME = 'UpdateOrganizationAppearance';

    public function __construct(
        private readonly RoomCatalogueTransaction $tx,
        private readonly OrganizationAppearanceRepositoryInterface $appearance,
    ) {}

    public function handle(Command $command): UpdateOrganizationAppearanceResult
    {
        assert($command instanceof UpdateOrganizationAppearanceCommand);
        $replayed = $this->tx->replayed($command->idempotencyKey, self::COMMAND_NAME);
        if ($replayed !== null) {
            return UpdateOrganizationAppearanceResult::fromIdempotency($replayed);
        }

        if (! in_array($command->target, OrganizationAppearanceRepositoryInterface::TARGETS, true)) {
            return UpdateOrganizationAppearanceResult::failure('appearance.target_invalid');
        }
        if (! $this->appearance->belongsToSchool($command->target, $command->schoolId, $command->id)) {
            return UpdateOrganizationAppearanceResult::failure('appearance.not_found');
        }
        $appearance = DisplayAppearance::of($command->abbreviation, $command->colorHue);
        $error = $appearance->rejection();
        if ($error !== null) {
            return UpdateOrganizationAppearanceResult::failure($error);
        }

        return $this->tx->run(
            UpdateOrganizationAppearanceResult::class,
            $command->idempotencyKey,
            self::COMMAND_NAME,
            $command->target,
            $command->schoolId,
            'appearance',
            function (string $at) use ($command, $appearance): int {
                $this->appearance->setAppearance($command->target, $command->id, $appearance, $at);

                return $command->id;
            },
        );
    }
}
