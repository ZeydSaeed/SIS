<?php

namespace App\Infrastructure\Persistence\Imports;

use App\Database\SchemaHelper;
use App\Domain\Imports\Repositories\ImportBatchRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class EloquentImportBatchRepository implements ImportBatchRepositoryInterface
{
    private const BATCH_COLUMNS = ['id', 'school_id', 'academic_year_id', 'kind', 'status', 'file_name', 'storage_key', 'total_rows', 'valid_rows',
        'error_rows', 'duplicate_rows', 'result', 'error', 'created_by', 'created_at', 'committed_at'];

    public function create(int $schoolId, ?int $academicYearId, string $kind, string $fileName, string $storageKey, ?int $userId, string $at): int
    {
        $this->bindSchool($schoolId);

        return (int) DB::table($this->batches())->insertGetId([
            'school_id' => $schoolId, 'academic_year_id' => $academicYearId, 'kind' => $kind, 'status' => self::PARSING,
            'file_name' => mb_substr($fileName, 0, 255), 'storage_key' => $storageKey, 'created_by' => $userId, 'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    public function find(int $schoolId, int $batchId): ?array
    {
        $this->bindSchool($schoolId);
        $row = DB::table($this->batches())->where('school_id', $schoolId)->where('id', $batchId)->first(self::BATCH_COLUMNS);

        return $row === null ? null : self::batch($row);
    }

    public function latest(int $schoolId, int $limit): array
    {
        $this->bindSchool($schoolId);

        return DB::table($this->batches())->where('school_id', $schoolId)->orderByDesc('id')->limit($limit)->get(self::BATCH_COLUMNS)
            ->map(static fn (object $row): array => self::batch($row))->all();
    }

    public function setStatus(int $schoolId, int $batchId, int $status, ?string $error, string $at): void
    {
        $this->bindSchool($schoolId);
        DB::table($this->batches())->where('school_id', $schoolId)->where('id', $batchId)
            ->update(['status' => $status, 'error' => $error === null ? null : mb_substr($error, 0, 2000), 'updated_at' => $at]);
    }

    public function storeParsed(int $schoolId, int $batchId, array $rows, string $at): void
    {
        $this->bindSchool($schoolId);
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table($this->rowsTable())->insert(array_map(static fn (array $r): array => [
                'school_id' => $schoolId,
                'batch_id' => $batchId,
                'row_number' => $r['row_number'],
                'data' => json_encode($r['data'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'action' => $r['action'],
                'status' => $r['status'],
                'errors' => $r['errors'] === [] ? null : json_encode($r['errors'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'entity_id' => $r['entity_id'],
                'created_at' => $at,
                'updated_at' => $at,
            ], $chunk));
        }
        $count = static fn (int $status): int => count(array_filter($rows, static fn (array $r): bool => $r['status'] === $status));
        DB::table($this->batches())->where('school_id', $schoolId)->where('id', $batchId)->update([
            'status' => self::PREVIEWED,
            'total_rows' => count($rows),
            'valid_rows' => $count(self::ROW_VALID),
            'error_rows' => $count(self::ROW_ERROR),
            'duplicate_rows' => $count(self::ROW_DUPLICATE),
            'error' => null,
            'updated_at' => $at,
        ]);
    }

    public function rows(int $schoolId, int $batchId, ?int $status, int $page, int $perPage): array
    {
        $this->bindSchool($schoolId);
        $query = DB::table($this->rowsTable())->where('school_id', $schoolId)->where('batch_id', $batchId)
            ->when($status !== null, fn ($q) => $q->where('status', $status));
        $total = (clone $query)->count();

        return [
            'rows' => $query->orderBy('row_number')->forPage(max(1, $page), $perPage)
                ->get(['id', 'row_number', 'data', 'action', 'status', 'errors', 'entity_id'])
                ->map(static fn (object $r): array => self::row($r))->all(),
            'total' => $total,
        ];
    }

    public function rowsWithStatus(int $schoolId, int $batchId, array $statuses): array
    {
        $this->bindSchool($schoolId);

        return DB::table($this->rowsTable())->where('school_id', $schoolId)->where('batch_id', $batchId)->whereIn('status', $statuses)
            ->orderBy('row_number')->get(['id', 'row_number', 'data', 'action', 'status', 'errors', 'entity_id'])
            ->map(static fn (object $r): array => self::row($r))->all();
    }

    public function markRow(int $schoolId, int $rowId, int $status, ?int $entityId, array $errors, string $at): void
    {
        $this->bindSchool($schoolId);
        DB::table($this->rowsTable())->where('school_id', $schoolId)->where('id', $rowId)->update([
            'status' => $status,
            'entity_id' => $entityId,
            'errors' => $errors === [] ? null : json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'updated_at' => $at,
        ]);
    }

    public function finishCommit(int $schoolId, int $batchId, array $result, ?int $userId, string $at): void
    {
        $this->bindSchool($schoolId);
        DB::table($this->batches())->where('school_id', $schoolId)->where('id', $batchId)->update([
            'status' => self::COMMITTED,
            'result' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'committed_by' => $userId,
            'committed_at' => $at,
            'updated_at' => $at,
        ]);
    }

    /** @return array<string, mixed> */
    private static function batch(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'school_id' => (int) $row->school_id,
            'academic_year_id' => $row->academic_year_id !== null ? (int) $row->academic_year_id : null,
            'kind' => (string) $row->kind,
            'status' => (int) $row->status,
            'file_name' => (string) $row->file_name,
            'storage_key' => (string) $row->storage_key,
            'total_rows' => (int) $row->total_rows,
            'valid_rows' => (int) $row->valid_rows,
            'error_rows' => (int) $row->error_rows,
            'duplicate_rows' => (int) $row->duplicate_rows,
            'result' => $row->result !== null ? (array) json_decode((string) $row->result, true) : null,
            'error' => $row->error !== null ? (string) $row->error : null,
            'created_by' => $row->created_by !== null ? (int) $row->created_by : null,
            'created_at' => (string) $row->created_at,
            'committed_at' => $row->committed_at !== null ? (string) $row->committed_at : null,
        ];
    }

    /** @return array{id: int, row_number: int, data: array<string, mixed>, action: int, status: int, errors: list<string>, entity_id: int|null} */
    private static function row(object $r): array
    {
        return [
            'id' => (int) $r->id,
            'row_number' => (int) $r->row_number,
            'data' => (array) json_decode((string) $r->data, true),
            'action' => (int) $r->action,
            'status' => (int) $r->status,
            'errors' => $r->errors !== null ? array_values((array) json_decode((string) $r->errors, true)) : [],
            'entity_id' => $r->entity_id !== null ? (int) $r->entity_id : null,
        ];
    }

    private function batches(): string
    {
        return SchemaHelper::qualified('documents', 'import_batches');
    }

    private function rowsTable(): string
    {
        return SchemaHelper::qualified('documents', 'import_rows');
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
