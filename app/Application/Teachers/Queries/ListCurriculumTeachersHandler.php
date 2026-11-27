<?php

namespace App\Application\Teachers\Queries;

use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;

final class ListCurriculumTeachersHandler
{
    public function __construct(
        private readonly TeacherRepositoryInterface $teachers,
    ) {}

    /**
     * @return array{
     *     byLine: array<int, list<array{assignment_id:int, curriculum_subject_id:int, teacher_id:int, teacher_name:string, employee_code:string, role:int}>>,
     *     options: list<array{id:int, name:string, employee_code:string}>
     * }
     */
    public function handle(ListCurriculumTeachersQuery $query): array
    {
        $byLine = [];
        foreach ($this->teachers->listCurriculumLineTeachers($query->schoolId, $query->curriculumId) as $row) {
            $byLine[$row['curriculum_subject_id']][] = $row;
        }

        return [
            'byLine' => $byLine,
            'options' => $this->teachers->listTeacherOptions($query->schoolId, $query->academicYearId),
        ];
    }
}
