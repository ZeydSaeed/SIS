<?php

namespace App\Domain\Timetable\Repositories;

/** timetable.versions + immutable timetable.version_entries. */
interface TimetableVersionRepositoryInterface
{
    /** Snapshots the active working grid of the school-year as a new draft version; returns its id. */
    public function snapshotWorkingGrid(int $schoolId, int $academicYearId, string $name, ?string $reason, string $fingerprint, array $quality, ?int $parentVersionId, ?int $generationRunId, ?int $userId): int;

    /** @return array<string, mixed>|null */
    public function find(int $schoolId, int $versionId): ?array;

    /** @return list<array<string, mixed>> */
    public function listForYear(int $schoolId, int $academicYearId): array;

    /** @return list<array<string, mixed>> entries of a version (section, group, day, period, week, subject, teacher, co-teacher, room, activity, source schedule) */
    public function entries(int $schoolId, int $versionId, ?int $sectionId = null, ?int $teacherId = null): array;

    public function setStatus(int $schoolId, int $versionId, int $from, int $to, array $fields = []): bool;

    /** The published version with the latest effective_from, if any. */
    public function currentPublished(int $schoolId, int $academicYearId): ?array;

    /** Marks earlier published versions superseded, ending them the day before `effectiveFrom`. */
    public function supersedePublished(int $schoolId, int $academicYearId, int $exceptVersionId, string $effectiveFrom): int;

    public function findByApprovalRequest(int $schoolId, int $approvalRequestId): ?array;
}
