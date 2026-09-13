<?php

namespace App\Application\Curriculum\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Curriculum\Results\DeactivateSubjectPrerequisiteResult;
use App\Domain\Curriculum\Events\SubjectPrerequisiteDeactivated;
use App\Domain\Curriculum\Repositories\PrerequisiteRepositoryInterface;
use App\Domain\Curriculum\Support\CurriculumIdempotencyGuard;

final class DeactivateSubjectPrerequisiteHandler implements CommandHandler
{
    private const COMMAND_NAME = 'DeactivateSubjectPrerequisite';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly PrerequisiteRepositoryInterface $prerequisites,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): DeactivateSubjectPrerequisiteResult
    {
        assert($command instanceof DeactivateSubjectPrerequisiteCommand);
        $key = CurriculumIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return DeactivateSubjectPrerequisiteResult::fromIdempotency((int) $cached['prerequisite_id']);
        }

        $existing = $this->prerequisites->findActive($command->prerequisiteId);
        if ($existing === null) {
            return DeactivateSubjectPrerequisiteResult::failure(['curriculum.prerequisite_not_found']);
        }

        $this->unitOfWork->transaction(function () use ($command, $key, $existing): void {
            $this->prerequisites->deactivate($command->prerequisiteId);
            $this->outbox->stage(new SubjectPrerequisiteDeactivated(
                $command->prerequisiteId,
                $existing->subjectId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, [
                'prerequisite_id' => $command->prerequisiteId,
            ]);
        });

        return DeactivateSubjectPrerequisiteResult::success($command->prerequisiteId);
    }
}
