<?php

namespace App\Application\Curriculum\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Curriculum\Results\AddSubjectPrerequisiteResult;
use App\Domain\Curriculum\Events\SubjectPrerequisiteAdded;
use App\Domain\Curriculum\Repositories\PrerequisiteRepositoryInterface;
use App\Domain\Curriculum\Services\AddSubjectPrerequisiteGuard;
use App\Domain\Curriculum\Support\CurriculumIdempotencyGuard;

final class AddSubjectPrerequisiteHandler implements CommandHandler
{
    private const COMMAND_NAME = 'AddSubjectPrerequisite';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly PrerequisiteRepositoryInterface $prerequisites,
        private readonly AddSubjectPrerequisiteGuard $guard,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): AddSubjectPrerequisiteResult
    {
        assert($command instanceof AddSubjectPrerequisiteCommand);
        $key = CurriculumIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return AddSubjectPrerequisiteResult::fromIdempotency((int) $cached['prerequisite_id']);
        }

        $error = $this->guard->rejectionCode($command->subjectId, $command->prerequisiteSubjectId);
        if ($error !== null) {
            return AddSubjectPrerequisiteResult::failure([$error]);
        }

        $id = $this->unitOfWork->transaction(function () use ($command, $key): int {
            $id = $this->prerequisites->addOrReactivate(
                $command->subjectId,
                $command->prerequisiteSubjectId,
                (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
            );
            $this->outbox->stage(new SubjectPrerequisiteAdded(
                $id,
                $command->subjectId,
                $command->prerequisiteSubjectId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['prerequisite_id' => $id]);

            return $id;
        });

        return AddSubjectPrerequisiteResult::success($id);
    }
}
