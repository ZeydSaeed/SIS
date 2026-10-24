<?php

namespace App\Application\Results\Contracts;

/**
 * What a student or guardian may see: the annual result and year GPA of each enrollment of their students, but only
 * while an active publication snapshots that exact official version. Unpublished results are never returned.
 */
interface PortalPublishedResultsReadRepositoryInterface
{
    /**
     * @param  list<int>  $studentIds  already authorized by the caller (own scope or verified guardian)
     * @return list<array{
     *     enrollment_id: int, student_name: string, student_code: string, year_name: string, class_name: string,
     *     annual: array{value: ?string, version: int, incomplete: bool, published_at: string}|null,
     *     gpa: array{value: ?string, version: int, incomplete: bool, published_at: string}|null,
     *     transcript: array{value: ?string, version: int, incomplete: bool, published_at: string}|null,
     *     terms: list<array{term_name: string, subject_name: string, value: ?string, version: int, incomplete: bool, published_at: string}>
     * }>
     */
    public function forStudents(int $schoolId, array $studentIds): array;
}
