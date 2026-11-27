<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\UnitOfWork;
use App\Application\Teachers\Results\AssignCurriculumTeacherResult;
use App\Domain\Teachers\Contracts\CurriculumLineCatalogPort;
use App\Domain\Teachers\Data\TeachingAssignmentData;
use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;
use App\Domain\Teachers\Services\CurriculumTeacherGuard;
use App\Domain\Teachers\Services\TeachingAssignmentGuard;
use App\Domain\Teachers\Support\TeacherIdempotencyGuard;
use App\Domain\Teachers\ValueObjects\TeachingRole;

/**
 * Records the teacher on the curriculum line as a teaching assignment (the one record of who teaches what where:
 * grade entry, the question bank and the timetable read it), with the line's place and the role. The subject is
 * also assigned to the teacher for the year (teacher_subjects) — same as «المعلمون».
 */
final class AssignCurriculumTeacherHandler implements CommandHandler
{
    private const COMMAND_NAME = 'AssignCurriculumTeacher';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly TeacherRepositoryInterface $teachers,
        private readonly CurriculumLineCatalogPort $lines,
        private readonly CurriculumTeacherGuard $roles,
        private readonly TeachingAssignmentGuard $placement,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): AssignCurriculumTeacherResult
    {
        assert($command instanceof AssignCurriculumTeacherCommand);
        $key = TeacherIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return AssignCurriculumTeacherResult::fromIdempotency((int) $cached['assignment_id']);
        }

        return $this->unitOfWork->transaction(function () use ($command, $key): AssignCurriculumTeacherResult {
            // Locked: two people setting the main teacher of one line at once get one answer each.
            $line = $this->lines->findLine($command->schoolId, $command->curriculumSubjectId, lock: true);
            if ($line === null) {
                return AssignCurriculumTeacherResult::failure('teachers.curriculum_line_not_found');
            }
            if (! $this->teachers->belongsToSchool($command->teacherId, $command->schoolId, $line->academicYearId)) {
                return AssignCurriculumTeacherResult::failure('teachers.not_in_school_year');
            }

            $mainId = $this->teachers->lineMainAssignmentId($command->schoolId, $line->lineId);
            $error = $this->roles->assignError(
                $line,
                $command->role,
                $mainId !== null,
                $this->teachers->teacherActiveOnLine($command->schoolId, $line->lineId, $command->teacherId),
                $command->replaceMain,
            );
            if ($error !== null) {
                return AssignCurriculumTeacherResult::failure($error);
            }

            $now = new \DateTimeImmutable;
            assert($line->branchId !== null);
            $data = new TeachingAssignmentData(
                teacherId: $command->teacherId,
                schoolId: $command->schoolId,
                academicYearId: $line->academicYearId,
                subjectId: $line->subjectId,
                branchId: $line->branchId,
                departmentId: $line->departmentId,
                classId: $line->classId,
                sectionId: $line->sectionId,
                effectiveFrom: $now->format('Y-m-d'),
                at: $now->format('Y-m-d H:i:s'),
                role: $command->role,
                curriculumSubjectId: $line->lineId,
            );
            $placementError = $this->placement->error($data);
            if ($placementError !== null) {
                return AssignCurriculumTeacherResult::failure($placementError);
            }
            if ($this->teachers->activeTeachingAssignmentExists($data)) {
                // Already teaches it here (set on «المعلمون»).
                return AssignCurriculumTeacherResult::failure('teachers.assignment_exists');
            }

            if ($command->role === TeachingRole::Main && $mainId !== null) {
                // «تغيير المدرس الرئيسي»: the previous main teacher's row is ended (history kept), then replaced.
                $this->teachers->endTeachingAssignment($command->schoolId, $mainId, $now->format('Y-m-d'), $data->at);
            }
            if ($this->teachers->findSubjectAssignmentId($command->teacherId, $line->subjectId, $line->academicYearId, $command->schoolId) === null) {
                $this->teachers->assignSubject($command->teacherId, $line->subjectId, $line->academicYearId, $command->schoolId, $data->at);
            }
            $id = $this->teachers->addTeachingAssignment($data);
            $this->idempotency->store($key, self::COMMAND_NAME, ['assignment_id' => $id]);

            return AssignCurriculumTeacherResult::success($id);
        });
    }
}
