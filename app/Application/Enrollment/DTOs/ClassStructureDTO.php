<?php

namespace App\Application\Enrollment\DTOs;

/** «الصفوف والشعب» page: the school's classes for a year, their sections and active enrollment counts. */
final readonly class ClassStructureDTO
{
    /**
     * @param  list<ClassDTO>  $classes
     * @param  array<int, list<SectionDTO>>  $sectionsByClass  class id → sections
     * @param  array<int, int>  $enrolledBySection  section id → active enrollments
     */
    public function __construct(
        public array $classes,
        public array $sectionsByClass,
        public array $enrolledBySection,
    ) {}
}
