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

        $id = $this->unitOfWork->transaction(function () use ($command, $key): int {
            $id = $this->subjects->create(
                $command->code,
                $command->name,
                $command->nameEn,
                $command->subjectType,
                $command->creditHours,
                $command->maxGrade,
                $command->passGrade,
                (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
            );
            $this->outbox->stage(new SubjectCreated($id, $command->code, new \DateTimeImmutable));
            $this->idempotency->store($key, self::COMMAND_NAME, ['subject_id' => $id]);

            return $id;
        });

        return CreateSubjectResult::success($id);
    }
}
