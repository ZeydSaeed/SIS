<?php

namespace App\Application\Teachers\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Teachers\DTOs\TeacherRosterDTO;
use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;
use App\Domain\Teachers\Repositories\TeacherRosterReadRepositoryInterface;

/**
 * One school's teaching staff for a year: three queries (teachers, subjects, assignments), no N+1.
 * A school roster is bounded (tens to a few hundred teachers); ROSTER_LIMIT caps the payload.
 */
final class GetTeacherRosterHandler implements QueryHandler
{
    public const ROSTER_LIMIT = 500;

    public function __construct(
        private readonly TeacherRosterReadRepositoryInterface $roster,
        private readonly TeacherRepositoryInterface $teachers,
    ) {}

    public function handle(Query $query): TeacherRosterDTO
    {
        assert($query instanceof GetTeacherRosterQuery);

        $page = $this->roster->roster($query->schoolId, $query->academicYearId, self::ROSTER_LIMIT);

        $subjectIdsByTeacher = [];
        foreach ($this->teachers->listSubjectAssignmentsForSchool($query->schoolId, $query->academicYearId) as $assignment) {
            $subjectIdsByTeacher[$assignment->teacherId][] = $assignment->subjectId;
        }

        $assignmentsByTeacher = [];
        foreach ($this->roster->teachingAssignments($query->schoolId, $query->academicYearId) as $row) {
            $assignmentsByTeacher[$row['teacher_id']][] = $row;
        }

        return new TeacherRosterDTO(
            teachers: $page['items'],
            total: $page['total'],
            subjectIdsByTeacher: $subjectIdsByTeacher,
            assignmentsByTeacher: $assignmentsByTeacher,
            curriculumSubjects: $this->roster->curriculumSubjectIds($query->schoolId, $query->academicYearId),
        );
    }
}
