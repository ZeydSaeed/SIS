<?php

namespace App\Application\Curriculum\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Curriculum\Results\ReactivateCurriculumResult;
use App\Domain\Curriculum\Events\CurriculumReactivated;
use App\Domain\Curriculum\Repositories\CurriculumRepositoryInterface;
use App\Domain\Curriculum\Support\CurriculumIdempotencyGuard;

final class ReactivateCurriculumHandler implements CommandHandler
{
    private const COMMAND_NAME = 'ReactivateCurriculum';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly CurriculumRepositoryInterface $curricula,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): ReactivateCurriculumResult
    {
        assert($command instanceof ReactivateCurriculumCommand);
        $key = CurriculumIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return ReactivateCurriculumResult::fromIdempotency((int) $cached['curriculum_id']);
        }

        $inactive = $this->curricula->findInactiveInSchool($command->schoolId, $command->curriculumId);
        if ($inactive === null) {
            return ReactivateCurriculumResult::failure(['curriculum.curriculum_not_found']);
        }
        // One active curriculum per branch / department / section, or per class when it names no section.
        $duplicate = $inactive->branchId !== null && ($inactive->sectionId !== null
            ? $this->curricula->activeScopeExists($command->schoolId, $inactive->academicYearId, $inactive->branchId, $inactive->departmentId, $inactive->sectionId, $command->curriculumId)
            : ($inactive->classId !== null && $this->curricula->activeClassScopeExists($command->schoolId, $inactive->academicYearId, $inactive->branchId, $inactive->departmentId, $inactive->classId, $command->curriculumId)));
        if ($duplicate) {
            return ReactivateCurriculumResult::failure(['curriculum.curriculum_exists']);
        }

        $this->unitOfWork->transaction(function () use ($command, $key): void {
            $this->curricula->reactivate($command->schoolId, $command->curriculumId);
            $this->outbox->stage(new CurriculumReactivated(
                $command->curriculumId,
                $command->schoolId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, [
                'curriculum_id' => $command->curriculumId,
            ]);
        });

        return ReactivateCurriculumResult::success($command->curriculumId);
    }
}
