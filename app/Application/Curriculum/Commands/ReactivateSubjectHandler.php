<?php

namespace App\Application\Curriculum\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Curriculum\Results\ReactivateSubjectResult;
use App\Domain\Curriculum\Events\SubjectReactivated;
use App\Domain\Curriculum\Repositories\SubjectRepositoryInterface;
use App\Domain\Curriculum\Support\CurriculumIdempotencyGuard;

final class ReactivateSubjectHandler implements CommandHandler
{
    private const COMMAND_NAME = 'ReactivateSubject';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly SubjectRepositoryInterface $subjects,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): ReactivateSubjectResult
    {
        assert($command instanceof ReactivateSubjectCommand);
        $key = CurriculumIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return ReactivateSubjectResult::fromIdempotency((int) $cached['subject_id']);
        }

        if ($this->subjects->findInactive($command->subjectId) === null) {
            return ReactivateSubjectResult::failure(['curriculum.subject_not_found']);
        }

        $this->unitOfWork->transaction(function () use ($command, $key): void {
            $this->subjects->reactivate($command->subjectId);
            $this->outbox->stage(new SubjectReactivated($command->subjectId, new \DateTimeImmutable));
            $this->idempotency->store($key, self::COMMAND_NAME, ['subject_id' => $command->subjectId]);
        });

        return ReactivateSubjectResult::success($command->subjectId);
    }
}
