<?php

namespace App\Application\Curriculum\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Curriculum\Results\CreateSubjectResult;
use App\Domain\Curriculum\Events\SubjectCreated;
use App\Domain\Curriculum\Repositories\SubjectRepositoryInterface;
use App\Domain\Curriculum\Services\CreateSubjectGuard;
use App\Domain\Curriculum\Support\CurriculumIdempotencyGuard;
use App\Domain\Curriculum\ValueObjects\SubjectTypeSet;

final class CreateSubjectHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CreateSubject';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly SubjectRepositoryInterface $subjects,
        private readonly CreateSubjectGuard $guard,
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

        // «نوع المادة»: one or several, each with its hours; the first (ascending) is the primary type, and the
        // subject's hours are the types' total when any are given.
        $types = SubjectTypeSet::from(
            $command->subjectTypes !== [] ? $command->subjectTypes : [$command->subjectType],
            $command->subjectTypeHours,
        );
        if ($types === null) {
            return CreateSubjectResult::failure(['curriculum.subject_type_invalid']);
        }
        $creditHours = $types->totalHours() ?? $command->creditHours;

        $error = $this->guard->rejectionCode(
            $command->code,
            $command->name,
            $types->primary(),
            $creditHours,
            $command->maxGrade,
            $command->passGrade,
        );
        if ($error !== null) {
            return CreateSubjectResult::failure([$error]);
        }

        $prerequisitesText = $command->prerequisitesText !== null
            ? trim($command->prerequisitesText)
            : null;
        if ($prerequisitesText === '') {
            $prerequisitesText = null;
        }

        $id = $this->unitOfWork->transaction(function () use ($command, $key, $prerequisitesText, $types, $creditHours): int {
            $id = $this->subjects->create(
                $command->code,
                $command->name,
                $command->nameEn,
                $types->primary(),
                $creditHours,
                $command->maxGrade,
                $command->passGrade,
                (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
                $prerequisitesText,
            );
            $this->subjects->replaceTypes($id, $types->hours);
            $this->outbox->stage(new SubjectCreated($id, $command->code, new \DateTimeImmutable));
            $this->idempotency->store($key, self::COMMAND_NAME, ['subject_id' => $id]);

            return $id;
        });

        return CreateSubjectResult::success($id);
    }
}
