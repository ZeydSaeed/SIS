<?php

namespace App\Application\Results\Contracts;

/**
 * Publication records of official results (results.result_publications). A publication never changes result rows; it
 * only records that an official annual result / year GPA of a class (or section) is visible to students.
 */
interface ResultPublicationRepositoryInterface
{
    /**
     * Active publications of the class and kind that overlap the scope: a class-wide scope overlaps every section, a
     * section scope overlaps the same section and the class-wide one.
     *
     * @return list<int> publication ids
     */
    public function activeOverlapping(int $schoolId, int $academicYearId, int $classId, ?int $sectionId, string $kind, ?int $termId = null, ?int $subjectId = null): array;

    /**
     * @param  list<array{enrollment_id: int, version: int, value: ?string}>  $snapshot
     * @return int the new publication id
     */
    public function insert(int $schoolId, int $academicYearId, int $classId, ?int $sectionId, string $kind, ?int $termId, ?int $subjectId, array $snapshot, string $fingerprint, ?int $userId, ?string $correlationId): int;

    /** @return bool false when the publication is not an active one of the school */
    public function withdraw(int $schoolId, int $publicationId, ?int $userId, string $reason): bool;

    /**
     * Inside the publishing transaction: locks (FOR SHARE) the current official rows the snapshot names, so a concurrent
     * rebuild — which supersedes them with an UPDATE — waits until the publication commits. False when any entry is no
     * longer the current official version (the snapshot is stale and must not be published).
     *
     * @param  list<array{enrollment_id: int, version: int, value: ?string}>  $snapshot
     */
    public function lockSnapshot(int $schoolId, int $academicYearId, string $kind, array $snapshot, ?int $termId = null, ?int $subjectId = null): bool;

    /** Whether an active publication of the kind snapshots exactly this version of the enrollment's official result. */
    public function isPublished(int $schoolId, int $academicYearId, int $enrollmentId, string $kind, int $version, ?int $termId = null, ?int $subjectId = null): bool;

    /**
     * Every active publication of the school with how many of its published entries no longer match the current official
     * result (same condition as `drifted` on the sheets). Read-only; the caller sets the school context.
     *
     * @return list<array{publication_id: int, academic_year_id: int, kind: string, class_id: int, section_id: ?int, term_id: ?int, subject_id: ?int, participants: int, drifted: int, published_at: string}>
     */
    public function activeWithDrift(int $schoolId): array;

    /**
     * Active publications of a class, for the sheet.
     *
     * `drifted` counts published entries whose official result has since changed (a rebuild): the publication then
     * shows a result that is no longer the current official one, and must be withdrawn and published again.
     *
     * @return list<array{id: int, kind: string, section_id: ?int, term_id: ?int, subject_id: ?int, participants: int, published_at: string, published_by_name: ?string, drifted: int}>
     */
    public function activeForClass(int $schoolId, int $academicYearId, int $classId): array;
}
