<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\UnitOfWork;
use App\Application\Enrollment\Results\UpdateStructureAppearanceResult;
use App\Domain\Enrollment\Repositories\EnrollmentStructureRepositoryInterface;
use App\Domain\Enrollment\Support\EnrollmentIdempotencyGuard;
use App\Domain\Shared\ValueObjects\DisplayAppearance;

final class UpdateStructureAppearanceHandler implements CommandHandler
{
    private const COMMAND_NAME = 'UpdateStructureAppearance';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly EnrollmentStructureRepositoryInterface $structure,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): UpdateStructureAppearanceResult
    {
        assert($command instanceof UpdateStructureAppearanceCommand);
        $key = EnrollmentIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return UpdateStructureAppearanceResult::fromIdempotency((int) $cached['id']);
        }

        if (! in_array($command->target, UpdateStructureAppearanceCommand::TARGETS, true)) {
            return UpdateStructureAppearanceResult::failure('appearance.target_invalid');
        }
        $exists = $command->target === 'class'
            ? $this->structure->findClass($command->schoolId, $command->id) !== null
            : $this->structure->findSection($command->schoolId, $command->id) !== null;
        if (! $exists) {
            return UpdateStructureAppearanceResult::failure('appearance.not_found');
        }
        $appearance = DisplayAppearance::of($command->abbreviation, $command->colorHue);
        $error = $appearance->rejection();
        if ($error !== null) {
            return UpdateStructureAppearanceResult::failure($error);
        }

        $this->unitOfWork->transaction(function () use ($command, $key, $appearance): void {
            $this->structure->setAppearance($command->target, $command->schoolId, $command->id, $appearance, now()->toIso8601String());
            $this->idempotency->store($key, self::COMMAND_NAME, ['id' => $command->id]);
        });

        return UpdateStructureAppearanceResult::success($command->id);
    }
}
