<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\UnitOfWork;
use App\Application\Teachers\Results\EndCurriculumTeacherResult;
use App\Domain\Teachers\Contracts\CurriculumLineCatalogPort;
use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;
use App\Domain\Teachers\Services\CurriculumTeacherGuard;
use App\Domain\Teachers\ValueObjects\TeachingAssignmentStatus;

final class EndCurriculumTeacherHandler implements CommandHandler
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly TeacherRepositoryInterface $teachers,
        private readonly CurriculumLineCatalogPort $lines,
        private readonly CurriculumTeacherGuard $roles,
    ) {}

    public function handle(Command $command): EndCurriculumTeacherResult
    {
        assert($command instanceof EndCurriculumTeacherCommand);

        return $this->unitOfWork->transaction(function () use ($command): EndCurriculumTeacherResult {
            if ($this->lines->findLine($command->schoolId, $command->curriculumSubjectId, lock: true) === null) {
                return EndCurriculumTeacherResult::failure('teachers.curriculum_line_not_found');
            }
            $assignment = $this->teachers->findTeachingAssignment($command->schoolId, $command->assignmentId);
            if ($assignment === null || $assignment['curriculum_subject_id'] !== $command->curriculumSubjectId) {
                return EndCurriculumTeacherResult::failure('teachers.assignment_not_found');
            }
            if ($assignment['status'] !== TeachingAssignmentStatus::Active) {
                return EndCurriculumTeacherResult::failure('teachers.assignment_not_active');
            }
            [, $assistants] = $this->teachers->lineTeacherCounts($command->schoolId, $command->curriculumSubjectId);
            $error = $this->roles->endError($assignment['role'], $assistants);
            if ($error !== null) {
                return EndCurriculumTeacherResult::failure($error);
            }

            $now = new \DateTimeImmutable;
            $ended = $this->teachers->endTeachingAssignment(
                $command->schoolId,
                $command->assignmentId,
                $now->format('Y-m-d'),
                $now->format('Y-m-d H:i:s'),
            );

            return $ended
                ? EndCurriculumTeacherResult::success($command->assignmentId)
                : EndCurriculumTeacherResult::failure('teachers.assignment_not_active');
        });
    }
}
