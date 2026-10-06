<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\UnitOfWork;
use App\Application\Teachers\Results\EndTeachingAssignmentResult;
use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;
use App\Domain\Teachers\ValueObjects\TeachingAssignmentStatus;

/** Ends a teaching assignment (status 2 + effective_to today) — the history row stays. */
final class EndTeachingAssignmentHandler implements CommandHandler
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly TeacherRepositoryInterface $teachers,
    ) {}

    public function handle(Command $command): EndTeachingAssignmentResult
    {
        assert($command instanceof EndTeachingAssignmentCommand);

        $assignment = $this->teachers->findTeachingAssignment($command->schoolId, $command->assignmentId);
        if ($assignment === null || $assignment['teacher_id'] !== $command->teacherId) {
            return EndTeachingAssignmentResult::failure('teachers.assignment_not_found');
        }

        if ($assignment['status'] !== TeachingAssignmentStatus::Active) {
            return EndTeachingAssignmentResult::failure('teachers.assignment_not_active');
        }

        $now = new \DateTimeImmutable;
        $ended = $this->unitOfWork->transaction(fn (): bool => $this->teachers->endTeachingAssignment(
            $command->schoolId,
            $command->assignmentId,
            $now->format('Y-m-d'),
            $now->format('Y-m-d H:i:s'),
        ));

        return $ended
            ? EndTeachingAssignmentResult::success($command->assignmentId)
            : EndTeachingAssignmentResult::failure('teachers.assignment_not_active');
    }
}
