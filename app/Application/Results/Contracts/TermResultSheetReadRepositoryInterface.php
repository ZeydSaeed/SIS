<?php

namespace App\Application\Results\Contracts;

/**
 * Read side of the term-results sheet: per student of a class (or section) who sat the term's sessions of a subject —
 * grade counts and the current operational / official term result.
 */
interface TermResultSheetReadRepositoryInterface
{
    /** @return list<array{id: int, name: string}> */
    public function terms(int $academicYearId): array;

    /**
     * Active classes of the school-year with their active sections.
     *
     * @return list<array{id: int, name: string, sections: list<array{id: int, name: string}>}>
     */
    public function classes(int $schoolId, int $academicYearId): array;

    public function termInYear(int $termId, int $academicYearId): bool;

    public function classInSchoolYear(int $classId, int $schoolId, int $academicYearId): bool;

    public function sectionInClass(int $sectionId, int $classId): bool;

    /**
     * Subjects with at least one non-cancelled session in the term's exams of the school.
     *
     * @return list<array{id: int, name: string}>
     */
    public function subjects(int $schoolId, int $academicYearId, int $termId): array;

    /**
     * Active enrollments of the class (or section) seated in the term's sessions of the subject.
     *
     * @return list<array{
     *     enrollment_id: int, student_name: string, student_code: string, section_name: string,
     *     seats: int, current_grades: int, finalized_grades: int,
     *     operational: array{version: int, weighted_total: ?string, incomplete: bool}|null,
     *     official: array{version: int, weighted_total: ?string, incomplete: bool}|null
     * }>
     */
    public function rows(int $schoolId, int $academicYearId, int $termId, int $subjectId, int $classId, ?int $sectionId): array;
}
