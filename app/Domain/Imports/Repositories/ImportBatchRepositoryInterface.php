<?php

namespace App\Domain\Imports\Repositories;

/**
 * «استيراد Excel» batches and their parsed rows (documents.import_batches / import_rows), always scoped to the school.
 * Batches end by status (never deleted).
 */
interface ImportBatchRepositoryInterface
{
    public const PARSING = 1;

    public const PREVIEWED = 2;

    public const COMMITTING = 3;

    public const COMMITTED = 4;

    public const FAILED = 5;

    public const CANCELLED = 6;

    public const ROW_VALID = 1;

    public const ROW_ERROR = 2;

    public const ROW_DUPLICATE = 3;

    public const ROW_COMMITTED = 4;

    public const ROW_FAILED = 5;

    public function create(int $schoolId, ?int $academicYearId, string $kind, string $fileName, string $storageKey, ?int $userId, string $at): int;

    /** @return array{id: int, school_id: int, academic_year_id: int|null, kind: string, status: int, file_name: string, storage_key: string, total_rows: int, valid_rows: int, error_rows: int, duplicate_rows: int, result: array<string, mixed>|null, error: string|null, created_by: int|null, created_at: string, committed_at: string|null}|null */
    public function find(int $schoolId, int $batchId): ?array;

    /** @return list<array<string, mixed>> the school's latest batches (newest first) */
    public function latest(int $schoolId, int $limit): array;

    public function setStatus(int $schoolId, int $batchId, int $status, ?string $error, string $at): void;

    /**
     * Replaces the parsed rows and the counts, then marks the batch previewed.
     *
     * @param  list<array{row_number: int, data: array<string, mixed>, action: int, status: int, errors: list<string>, entity_id: int|null}>  $rows
     */
    public function storeParsed(int $schoolId, int $batchId, array $rows, string $at): void;

    /** @return array{rows: list<array<string, mixed>>, total: int} */
    public function rows(int $schoolId, int $batchId, ?int $status, int $page, int $perPage): array;

    /** @return list<array{id: int, row_number: int, data: array<string, mixed>, action: int, status: int, errors: list<string>, entity_id: int|null}> */
    public function rowsWithStatus(int $schoolId, int $batchId, array $statuses): array;

    /** @param  list<string>  $errors */
    public function markRow(int $schoolId, int $rowId, int $status, ?int $entityId, array $errors, string $at): void;

    /** @param  array<string, mixed>  $result */
    public function finishCommit(int $schoolId, int $batchId, array $result, ?int $userId, string $at): void;
}
