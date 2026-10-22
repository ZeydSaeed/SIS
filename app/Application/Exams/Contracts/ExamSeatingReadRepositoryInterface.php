<?php

namespace App\Application\Exams\Contracts;

/** Read side of seating a class / section into an exam session. */
interface ExamSeatingReadRepositoryInterface
{
    /**
     * Active classes of the school-year with their active sections (the seating form's choices).
     *
     * @return list<array{id: int, name: string, sections: list<array{id: int, name: string}>}>
     */
    public function classes(int $schoolId, int $academicYearId): array;

    public function classInSchoolYear(int $classId, int $schoolId, int $academicYearId): bool;

    public function sectionInClass(int $sectionId, int $classId): bool;

    /**
     * Active enrollments of the class (optionally one section) in the school-year, whether each has the subject
     * actively assigned (enrollment.enrollment_subjects) and whether it already has a seat in the session.
     *
     * @return list<array{enrollment_id: int, takes_subject: bool, seated: bool}>
     */
    public function candidates(int $schoolId, int $academicYearId, int $classId, ?int $sectionId, int $examSessionId, int $subjectId): array;

    /**
     * Active seats (registered / confirmed / present) per session.
     *
     * @param  list<int>  $examSessionIds
     * @return array<int, int>
     */
    public function activeSeatCounts(int $schoolId, array $examSessionIds): array;
}
