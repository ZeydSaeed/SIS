<?php

namespace App\Application\Curriculum\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Curriculum\Results\ReactivateCurriculumSubjectResult;
use App\Domain\Curriculum\Events\CurriculumSubjectReactivated;
use App\Domain\Curriculum\Repositories\CurriculumRepositoryInterface;
use App\Domain\Curriculum\Support\CurriculumIdempotencyGuard;

final class ReactivateCurriculumSubjectHandler implements CommandHandler
{
    private const COMMAND_NAME = 'ReactivateCurriculumSubject';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly CurriculumRepositoryInterface $curricula,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): ReactivateCurriculumSubjectResult
    {
        assert($command instanceof ReactivateCurriculumSubjectCommand);
        $key = CurriculumIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return ReactivateCurriculumSubjectResult::fromIdempotency((int) $cached['link_id']);
        }

        $link = $this->curricula->findInactiveLink($command->schoolId, $command->linkId);
        if ($link === null) {
            return ReactivateCurriculumSubjectResult::failure(['curriculum.curriculum_subject_not_found']);
        }

        $this->unitOfWork->transaction(function () use ($command, $key, $link): void {
            $this->curricula->reactivateLink($command->schoolId, $command->linkId);
            $this->outbox->stage(new CurriculumSubjectReactivated(
                $command->linkId,
                $link->curriculumId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['link_id' => $command->linkId]);
        });

        return ReactivateCurriculumSubjectResult::success($command->linkId);
    }
}
