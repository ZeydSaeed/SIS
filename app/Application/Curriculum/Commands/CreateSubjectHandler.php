<?php

namespace App\Application\Curriculum\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Curriculum\Results\CreateSubjectResult;
use App\Domain\Curriculum\Events\SubjectCreated;
use App\Domain\Curriculum\Events\SubjectPrerequisiteAdded;
use App\Domain\Curriculum\Repositories\PrerequisiteRepositoryInterface;
use App\Domain\Curriculum\Repositories\SubjectRepositoryInterface;
use App\Domain\Curriculum\Services\AddSubjectPrerequisiteGuard;
use App\Domain\Curriculum\Services\CreateSubjectGuard;
use App\Domain\Curriculum\Support\CurriculumIdempotencyGuard;

final class CreateSubjectHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CreateSubject';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly SubjectRepositoryInterface $subjects,
        private readonly PrerequisiteRepositoryInterface $prerequisites,
        private readonly CreateSubjectGuard $guard,
        private readonly AddSubjectPrerequisiteGuard $prerequisiteGuard,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CreateSubjectResult
    {
        assert($command instanceof CreateSubjectCommand);
        $key = CurriculumIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return CreateSubjectResult::fromIdempotency((int) $cached['subject_id']);
        }

        $error = $this->guard->rejectionCode(
            $command->code,
            $command->name,
            $command->subjectType,
            $command->creditHours,
            $command->maxGrade,
            $command->passGrade,
        );
        if ($error !== null) {
            return CreateSubjectResult::failure([$error]);
        }

        $prerequisiteIds = array_values(array_unique(array_filter(
            $command->prerequisiteSubjectIds,
            static fn (int $id): bool => $id > 0,
        )));

        foreach ($prerequisiteIds as $prerequisiteSubjectId) {
            if (! $this->prerequisites->subjectExists($prerequisiteSubjectId)) {
                return CreateSubjectResult::failure(['curriculum.prerequisite_subject_not_found']);
            }
        }

        try {
            $id = $this->unitOfWork->transaction(function () use ($command, $key, $prerequisiteIds): int {
                $createdAt = (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM);
                $id = $this->subjects->create(
                    $command->code,
                    $command->name,
                    $command->nameEn,
                    $command->subjectType,
                    $command->creditHours,
                    $command->maxGrade,
                    $command->passGrade,
                    $createdAt,
                );
                $this->outbox->stage(new SubjectCreated($id, $command->code, new \DateTimeImmutable));

                foreach ($prerequisiteIds as $prerequisiteSubjectId) {
                    $prereqError = $this->prerequisiteGuard->rejectionCode($id, $prerequisiteSubjectId);
                    if ($prereqError !== null) {
                        throw new \RuntimeException($prereqError);
                    }

                    $prerequisiteId = $this->prerequisites->addOrReactivate(
                        $id,
                        $prerequisiteSubjectId,
                        $createdAt,
                    );
                    $this->outbox->stage(new SubjectPrerequisiteAdded(
                        $prerequisiteId,
                        $id,
                        $prerequisiteSubjectId,
                        new \DateTimeImmutable,
                    ));
                }

                $this->idempotency->store($key, self::COMMAND_NAME, ['subject_id' => $id]);

                return $id;
            });
        } catch (\RuntimeException $exception) {
            $code = $exception->getMessage();
            if (str_starts_with($code, 'curriculum.')) {
                return CreateSubjectResult::failure([$code]);
            }

            throw $exception;
        }

        return CreateSubjectResult::success($id);
    }
}
