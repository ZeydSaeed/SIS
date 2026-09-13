<?php

namespace App\Application\Curriculum\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Curriculum\Results\DeactivateCurriculumSubjectResult;
use App\Domain\Curriculum\Events\CurriculumSubjectDeactivated;
use App\Domain\Curriculum\Repositories\CurriculumRepositoryInterface;
use App\Domain\Curriculum\Support\CurriculumIdempotencyGuard;

final class DeactivateCurriculumSubjectHandler implements CommandHandler
{
    private const COMMAND_NAME = 'DeactivateCurriculumSubject';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly CurriculumRepositoryInterface $curricula,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): DeactivateCurriculumSubjectResult
    {
        assert($command instanceof DeactivateCurriculumSubjectCommand);
        $key = CurriculumIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return DeactivateCurriculumSubjectResult::fromIdempotency((int) $cached['link_id']);
        }

        $link = $this->curricula->findActiveLink($command->schoolId, $command->linkId);
        if ($link === null) {
            return DeactivateCurriculumSubjectResult::failure(['curriculum.curriculum_subject_not_found']);
        }

        $this->unitOfWork->transaction(function () use ($command, $key, $link): void {
            $this->curricula->deactivateLink($command->schoolId, $command->linkId);
            $this->outbox->stage(new CurriculumSubjectDeactivated(
                $command->linkId,
                $link->curriculumId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['link_id' => $command->linkId]);
        });

        return DeactivateCurriculumSubjectResult::success($command->linkId);
    }
}
