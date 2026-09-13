<?php

namespace App\Application\Curriculum\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Curriculum\Results\UpdateSubjectResult;
use App\Domain\Curriculum\Events\SubjectUpdated;
use App\Domain\Curriculum\Repositories\SubjectRepositoryInterface;
use App\Domain\Curriculum\Services\UpdateSubjectGuard;
use App\Domain\Curriculum\Support\CurriculumIdempotencyGuard;

final class UpdateSubjectHandler implements CommandHandler
{
    private const COMMAND_NAME = 'UpdateSubject';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly SubjectRepositoryInterface $subjects,
        private readonly UpdateSubjectGuard $guard,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): UpdateSubjectResult
    {
        assert($command instanceof UpdateSubjectCommand);
        $key = CurriculumIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return UpdateSubjectResult::fromIdempotency((int) $cached['subject_id']);
        }

        $error = $this->guard->rejectionCode($command->subjectId, $command->fields);
        if ($error !== null) {
            return UpdateSubjectResult::failure([$error]);
        }

        $this->unitOfWork->transaction(function () use ($command, $key): void {
            $this->subjects->updateActive(
                $command->subjectId,
                $command->fields,
                (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
            );
            $this->outbox->stage(new SubjectUpdated($command->subjectId, new \DateTimeImmutable));
            $this->idempotency->store($key, self::COMMAND_NAME, ['subject_id' => $command->subjectId]);
        });

        return UpdateSubjectResult::success($command->subjectId);
    }
}
