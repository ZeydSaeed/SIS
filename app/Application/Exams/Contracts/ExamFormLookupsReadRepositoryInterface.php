<?php

namespace App\Application\Exams\Contracts;

/** Choices for the exam / exam-session web forms (existing records only — nothing is created here). */
interface ExamFormLookupsReadRepositoryInterface
{
    /** @return list<array{id: int, name: string}> */
    public function terms(int $academicYearId): array;

    /** @return list<array{id: int, name: string, weight_percentage: int}> */
    public function examTypes(int $schoolId): array;

    /**
     * Subjects of the school's active curricula for the academic year.
     *
     * @return list<array{id: int, name: string}>
     */
    public function subjects(int $schoolId, int $academicYearId): array;
}
