<?php

namespace App\Application\Curriculum\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Curriculum\Results\DeactivateSubjectResult;
use App\Domain\Curriculum\Events\SubjectDeactivated;
use App\Domain\Curriculum\Repositories\SubjectRepositoryInterface;
use App\Domain\Curriculum\Support\CurriculumIdempotencyGuard;

final class DeactivateSubjectHandler implements CommandHandler
{
    private const COMMAND_NAME = 'DeactivateSubject';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly SubjectRepositoryInterface $subjects,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): DeactivateSubjectResult
    {
        assert($command instanceof DeactivateSubjectCommand);
        $key = CurriculumIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return DeactivateSubjectResult::fromIdempotency((int) $cached['subject_id']);
        }

        if ($this->subjects->findActive($command->subjectId) === null) {
            return DeactivateSubjectResult::failure(['curriculum.subject_not_found']);
        }

        $this->unitOfWork->transaction(function () use ($command, $key): void {
            $this->subjects->deactivate($command->subjectId);
            $this->outbox->stage(new SubjectDeactivated($command->subjectId, new \DateTimeImmutable));
            $this->idempotency->store($key, self::COMMAND_NAME, [
                'subject_id' => $command->subjectId,
            ]);
        });

        return DeactivateSubjectResult::success($command->subjectId);
    }
}
