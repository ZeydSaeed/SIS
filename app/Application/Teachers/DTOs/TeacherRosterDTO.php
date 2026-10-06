<?php

namespace App\Application\Teachers\DTOs;

/**
 * The school's teachers for an academic year (read model of the «المعلمون» page).
 */
final readonly class TeacherRosterDTO
{
    /**
     * @param  list<array<string, mixed>>  $teachers  roster rows (TeacherRosterReadRepositoryInterface::roster)
     * @param  array<int, list<int>>  $subjectIdsByTeacher  teacher id → assigned subject ids (المواد المسندة)
     * @param  array<int, list<array<string, mixed>>>  $assignmentsByTeacher  teacher id → active teaching assignments
     * @param  array{general: list<int>, by_department: array<int, list<int>>}  $curriculumSubjects  subject picker
     */
    public function __construct(
        public array $teachers,
        public int $total,
        public array $subjectIdsByTeacher,
        public array $assignmentsByTeacher,
        public array $curriculumSubjects = ['general' => [], 'by_department' => []],
    ) {}
}
