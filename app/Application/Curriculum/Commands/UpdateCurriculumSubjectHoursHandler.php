<?php

namespace App\Application\Curriculum\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\UnitOfWork;
use App\Application\Curriculum\Results\UpdateCurriculumSubjectHoursResult;
use App\Domain\Curriculum\Repositories\CurriculumRepositoryInterface;
use App\Domain\Curriculum\Services\LinkCurriculumSubjectGuard;
use App\Domain\Curriculum\Support\CurriculumIdempotencyGuard;

final class UpdateCurriculumSubjectHoursHandler implements CommandHandler
{
    private const COMMAND_NAME = 'UpdateCurriculumSubjectHours';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly CurriculumRepositoryInterface $curricula,
        private readonly LinkCurriculumSubjectGuard $guard,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): UpdateCurriculumSubjectHoursResult
    {
        assert($command instanceof UpdateCurriculumSubjectHoursCommand);
        $key = CurriculumIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return UpdateCurriculumSubjectHoursResult::fromIdempotency((int) $cached['link_id']);
        }

        $error = $this->guard->hoursSplitError($command->theoryHours, $command->practicalHours);
        if ($error !== null) {
            return UpdateCurriculumSubjectHoursResult::failure([$error]);
        }
        if ($this->curricula->findActiveLink($command->schoolId, $command->linkId) === null) {
            return UpdateCurriculumSubjectHoursResult::failure(['curriculum.curriculum_subject_not_found']);
        }

        $this->unitOfWork->transaction(function () use ($command, $key): void {
            $this->curricula->updateLinkHours($command->schoolId, $command->linkId, $command->theoryHours, $command->practicalHours);
            $this->idempotency->store($key, self::COMMAND_NAME, ['link_id' => $command->linkId]);
        });

        return UpdateCurriculumSubjectHoursResult::success($command->linkId);
    }
}
