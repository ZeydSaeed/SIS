<?php

namespace App\Application\Teachers\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Teachers\DTOs\TeacherDTO;
use App\Application\Teachers\DTOs\TeacherRosterDTO;
use App\Domain\Teachers\Data\TeacherSnapshot;
use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;

/**
 * One school's teaching staff for a year: two queries (teachers, assignments), no N+1.
 * A school roster is bounded (tens to a few hundred teachers); ROSTER_LIMIT caps the payload.
 */
final class GetTeacherRosterHandler implements QueryHandler
{
    public const ROSTER_LIMIT = 500;

    public function __construct(
        private readonly TeacherRepositoryInterface $teachers,
    ) {}

    public function handle(Query $query): TeacherRosterDTO
    {
        assert($query instanceof GetTeacherRosterQuery);

        $page = $this->teachers->listForSchool($query->schoolId, $query->academicYearId, 1, self::ROSTER_LIMIT);

        $subjectIdsByTeacher = [];
        foreach ($this->teachers->listSubjectAssignmentsForSchool($query->schoolId, $query->academicYearId) as $assignment) {
            $subjectIdsByTeacher[$assignment->teacherId][] = $assignment->subjectId;
        }

        return new TeacherRosterDTO(
            teachers: array_map([$this, 'map'], $page['items']),
            total: $page['total'],
            subjectIdsByTeacher: $subjectIdsByTeacher,
        );
    }

    private function map(TeacherSnapshot $s): TeacherDTO
    {
        return new TeacherDTO(
            $s->id,
            $s->userId,
            $s->employeeCode,
            $s->nationalId,
            $s->firstName,
            $s->lastName,
            $s->fullName,
            $s->specializationField,
            $s->hireDate,
            $s->status,
            $s->schoolId,
            $s->academicYearId,
            $s->isPrimary,
        );
    }
}
