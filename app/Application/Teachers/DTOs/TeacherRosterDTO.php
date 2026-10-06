<?php

namespace App\Application\Teachers\DTOs;

/**
 * The school's teachers for an academic year with their subject assignments (teachers page).
 */
final readonly class TeacherRosterDTO
{
    /**
     * @param  list<TeacherDTO>  $teachers
     * @param  array<int, list<int>>  $subjectIdsByTeacher  teacher id → assigned subject ids
     */
    public function __construct(
        public array $teachers,
        public int $total,
        public array $subjectIdsByTeacher,
    ) {}
}
