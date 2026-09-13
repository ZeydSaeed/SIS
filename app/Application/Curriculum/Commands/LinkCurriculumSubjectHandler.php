<?php

namespace App\Application\Curriculum\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Curriculum\Results\LinkCurriculumSubjectResult;
use App\Domain\Curriculum\Events\CurriculumSubjectLinked;
use App\Domain\Curriculum\Repositories\CurriculumRepositoryInterface;
use App\Domain\Curriculum\Services\LinkCurriculumSubjectGuard;
use App\Domain\Curriculum\Support\CurriculumIdempotencyGuard;

final class LinkCurriculumSubjectHandler implements CommandHandler
{
    private const COMMAND_NAME = 'LinkCurriculumSubject';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly CurriculumRepositoryInterface $curricula,
        private readonly LinkCurriculumSubjectGuard $guard,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): LinkCurriculumSubjectResult
    {
        assert($command instanceof LinkCurriculumSubjectCommand);
        $key = CurriculumIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return LinkCurriculumSubjectResult::fromIdempotency((int) $cached['link_id']);
        }

        $error = $this->guard->rejectionCode(
            $command->schoolId,
            $command->curriculumId,
            $command->subjectId,
            $command->weeklyHours,
            $command->subjectOrder,
        );
        if ($error !== null) {
            return LinkCurriculumSubjectResult::failure([$error]);
        }

        $id = $this->unitOfWork->transaction(function () use ($command, $key): int {
            $id = $this->curricula->linkSubject(
                $command->schoolId,
                $command->curriculumId,
                $command->subjectId,
                $command->weeklyHours,
                $command->isRequired,
                $command->subjectOrder,
                (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
            );
            $this->outbox->stage(new CurriculumSubjectLinked(
                $id,
                $command->curriculumId,
                $command->subjectId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['link_id' => $id]);

            return $id;
        });

        return LinkCurriculumSubjectResult::success($id);
    }
}
