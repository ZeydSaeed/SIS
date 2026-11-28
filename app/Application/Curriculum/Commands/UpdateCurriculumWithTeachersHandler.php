<?php

namespace App\Application\Curriculum\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Curriculum\Results\UpdateCurriculumWithTeachersResult;
use App\Application\Curriculum\Support\CurriculumLineSync;
use App\Application\Curriculum\Support\CurriculumTeacherSync;
use App\Domain\Curriculum\Events\CurriculumUpdated;
use App\Domain\Curriculum\Repositories\CurriculumRepositoryInterface;
use App\Domain\Curriculum\Services\CreateCurriculumGuard;
use App\Domain\Curriculum\Support\CurriculumIdempotencyGuard;

/**
 * «تعديل منهج» — one transaction: the curriculum's year / place / name / «عدد المواد» (checked like a new one, itself
 * excepted), its subjects, then its teachers. When the year or place changes, the old assignments end first (they
 * belonged to the old place). Any refusal undoes everything and is returned.
 */
final class UpdateCurriculumWithTeachersHandler implements CommandHandler
{
    private const COMMAND_NAME = 'UpdateCurriculumWithTeachers';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly CurriculumRepositoryInterface $curricula,
        private readonly CreateCurriculumGuard $guard,
        private readonly CurriculumLineSync $lines,
        private readonly CurriculumTeacherSync $teachers,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): UpdateCurriculumWithTeachersResult
    {
        assert($command instanceof UpdateCurriculumWithTeachersCommand);
        $key = CurriculumIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return UpdateCurriculumWithTeachersResult::success((int) $cached['curriculum_id'], true);
        }

        try {
            return $this->unitOfWork->transaction(function () use ($command, $key): UpdateCurriculumWithTeachersResult {
                $this->updatePlace($command);
                $lineBySubject = $this->lines->sync($command->schoolId, $command->curriculumId, $command->subjectIds);
                $this->teachers->sync($command->schoolId, $lineBySubject, $command->teachers, $key);
                $this->outbox->stage(new CurriculumUpdated($command->curriculumId, $command->schoolId, ['all' => true], new \DateTimeImmutable));
                $this->idempotency->store($key, self::COMMAND_NAME, ['curriculum_id' => $command->curriculumId]);

                return UpdateCurriculumWithTeachersResult::success($command->curriculumId);
            });
        } catch (CurriculumCreationRefused $refused) {
            return UpdateCurriculumWithTeachersResult::failure($refused->errors !== [] ? $refused->errors : ['curriculum.curriculum_update_failed']);
        }
    }

    private function updatePlace(UpdateCurriculumWithTeachersCommand $command): void
    {
        $current = $this->curricula->findActiveInSchool($command->schoolId, $command->curriculumId)
            ?? throw new CurriculumCreationRefused(['curriculum.curriculum_not_found']);
        $error = $this->guard->rejectionCode(
            $command->schoolId,
            $command->academicYearId,
            $command->gradeLevelId,
            $command->name,
            null,
            $command->departmentId,
            $command->branchId,
            $command->classId,
            $current->sectionId,
            $command->plannedSubjectCount,
            $command->curriculumId,
            count(array_unique($command->subjectIds)),
        );
        if ($error !== null) {
            throw new CurriculumCreationRefused([$error]);
        }
        $moved = [$current->academicYearId, $current->branchId, $current->departmentId, $current->classId]
            !== [$command->academicYearId, $command->branchId, $command->departmentId, $command->classId];
        if ($moved) {
            $this->teachers->endAll($command->schoolId, array_map(
                static fn ($line): int => $line->id,
                $this->curricula->listLinks($command->schoolId, $command->curriculumId),
            ));
        }
        $this->curricula->updatePlacement($command->schoolId, $command->curriculumId, [
            'academic_year_id' => $command->academicYearId,
            'grade_level_id' => $command->gradeLevelId,
            'branch_id' => $command->branchId,
            'department_id' => $command->departmentId,
            'class_id' => $command->classId,
            'name' => $command->name,
            'planned_subject_count' => $command->plannedSubjectCount,
        ], (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM));
    }
}
