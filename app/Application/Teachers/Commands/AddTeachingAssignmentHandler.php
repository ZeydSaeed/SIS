<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\UnitOfWork;
use App\Application\Teachers\Results\AddTeachingAssignmentResult;
use App\Domain\Teachers\Data\TeachingAssignmentData;
use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;
use App\Domain\Teachers\Services\TeachingAssignmentGuard;
use App\Domain\Teachers\Support\TeacherIdempotencyGuard;

/**
 * Records where a teacher teaches a subject. The subject is also assigned to the teacher for the
 * year (teacher_subjects — المواد المسندة, used by the timetable) in the same transaction.
 */
final class AddTeachingAssignmentHandler implements CommandHandler
{
    private const COMMAND_NAME = 'AddTeachingAssignment';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly TeacherRepositoryInterface $teachers,
        private readonly TeachingAssignmentGuard $guard,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): AddTeachingAssignmentResult
    {
        assert($command instanceof AddTeachingAssignmentCommand);
        $key = TeacherIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return AddTeachingAssignmentResult::fromIdempotency((int) $cached['assignment_id']);
        }

        if (! $this->teachers->belongsToSchool($command->teacherId, $command->schoolId, $command->academicYearId)) {
            return AddTeachingAssignmentResult::failure('teachers.not_in_school_year');
        }

        if (! $this->teachers->subjectExists($command->subjectId)) {
            return AddTeachingAssignmentResult::failure('teachers.subject_not_found');
        }

        $now = new \DateTimeImmutable;
        $data = new TeachingAssignmentData(
            teacherId: $command->teacherId,
            schoolId: $command->schoolId,
            academicYearId: $command->academicYearId,
            subjectId: $command->subjectId,
            branchId: $command->branchId,
            departmentId: $command->departmentId,
            classId: $command->classId,
            sectionId: $command->sectionId,
            effectiveFrom: $now->format('Y-m-d'),
            at: $now->format('Y-m-d H:i:s'),
        );

        $error = $this->guard->error($data);
        if ($error !== null) {
            return AddTeachingAssignmentResult::failure($error);
        }

        if ($this->teachers->activeTeachingAssignmentExists($data)) {
            return AddTeachingAssignmentResult::failure('teachers.assignment_exists');
        }

        $assignmentId = $this->unitOfWork->transaction(function () use ($command, $data, $key): int {
            if ($this->teachers->findSubjectAssignmentId($command->teacherId, $command->subjectId, $command->academicYearId, $command->schoolId) === null) {
                $this->teachers->assignSubject($command->teacherId, $command->subjectId, $command->academicYearId, $command->schoolId, $data->at);
            }
            $id = $this->teachers->addTeachingAssignment($data);
            $this->idempotency->store($key, self::COMMAND_NAME, ['assignment_id' => $id]);

            return $id;
        });

        return AddTeachingAssignmentResult::success($assignmentId);
    }
}
