<?php

namespace App\Domain\Timetable\Repositories;

/** timetable.generation_runs — one active (queued / running) run per school-year, enforced by the database. */
interface GenerationRunRepositoryInterface
{
    /** @return int|null the new run id, or null when another run is already active */
    public function createQueued(int $schoolId, int $academicYearId, int $mode, bool $whatIf, array $scope, array $options, string $solver, ?int $userId): ?int;

    /** @return array<string, mixed>|null */
    public function find(int $schoolId, int $runId, bool $withPayload = false): ?array;

    public function activeRunId(int $schoolId, int $academicYearId): ?int;

    /** @return list<array<string, mixed>> recent runs without payloads */
    public function recent(int $schoolId, int $academicYearId, int $limit = 10): array;

    public function markRunning(int $schoolId, int $runId, string $fingerprint, array $snapshot): bool;

    public function reportProgress(int $schoolId, int $runId, array $progress): void;

    public function cancelRequested(int $schoolId, int $runId): bool;

    /**
     * @param  array{result: array, quality: array, hard_violations: int, soft_penalty: int, activities_total: int, placed: int, unplaced: int}  $outcome
     */
    public function finish(int $schoolId, int $runId, int $status, array $outcome): void;

    public function fail(int $schoolId, int $runId, string $error): void;

    public function requestCancel(int $schoolId, int $runId): bool;

    /** Moves a run from one status to another; false when it was not in `from`. */
    public function transition(int $schoolId, int $runId, int $from, int $to, ?int $userId = null): bool;
}
