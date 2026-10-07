<?php

namespace App\Infrastructure\Persistence\Timetable;

use App\Database\SchemaHelper;
use App\Domain\Timetable\Repositories\GenerationRunRepositoryInterface;
use App\Domain\Timetable\ValueObjects\GenerationRunStatus;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class EloquentGenerationRunRepository implements GenerationRunRepositoryInterface
{
    private const SUMMARY = ['id', 'academic_year_id', 'mode', 'status', 'is_what_if', 'scope', 'options', 'solver', 'progress', 'cancel_requested',
        'quality', 'hard_violations', 'soft_penalty', 'activities_total', 'placed', 'unplaced', 'error', 'requested_by', 'applied_by',
        'started_at', 'finished_at', 'applied_at', 'created_at', 'input_fingerprint'];

    public function createQueued(int $schoolId, int $academicYearId, int $mode, bool $whatIf, array $scope, array $options, string $solver, ?int $userId): ?int
    {
        $this->bindSchool($schoolId);
        try {
            // Own savepoint: a refused insert (another active run) must not abort the caller's transaction.
            return DB::transaction(fn (): int => (int) DB::table($this->table())->insertGetId([
                'school_id' => $schoolId,
                'academic_year_id' => $academicYearId,
                'mode' => $mode,
                'status' => GenerationRunStatus::Queued->value,
                'is_what_if' => $whatIf,
                'scope' => json_encode((object) $scope, JSON_THROW_ON_ERROR),
                'options' => json_encode((object) $options, JSON_THROW_ON_ERROR),
                'solver' => $solver,
                'requested_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    public function find(int $schoolId, int $runId, bool $withPayload = false): ?array
    {
        $this->bindSchool($schoolId);
        $columns = $withPayload ? [...self::SUMMARY, 'result', 'input_snapshot'] : self::SUMMARY;
        $row = DB::table($this->table())->where('school_id', $schoolId)->where('id', $runId)->first($columns);

        return $row === null ? null : $this->map($row);
    }

    public function activeRunId(int $schoolId, int $academicYearId): ?int
    {
        $this->bindSchool($schoolId);
        $id = DB::table($this->table())->where('school_id', $schoolId)->where('academic_year_id', $academicYearId)
            ->whereIn('status', [GenerationRunStatus::Queued->value, GenerationRunStatus::Running->value])->value('id');

        return $id !== null ? (int) $id : null;
    }

    public function recent(int $schoolId, int $academicYearId, int $limit = 10): array
    {
        $this->bindSchool($schoolId);

        return DB::table($this->table())->where('school_id', $schoolId)->where('academic_year_id', $academicYearId)
            ->orderByDesc('id')->limit($limit)->get(self::SUMMARY)
            ->map(fn (object $r): array => $this->map($r))->all();
    }

    public function markRunning(int $schoolId, int $runId, string $fingerprint, array $snapshot): bool
    {
        $this->bindSchool($schoolId);

        return DB::table($this->table())->where('school_id', $schoolId)->where('id', $runId)
            ->where('status', GenerationRunStatus::Queued->value)
            ->update([
                'status' => GenerationRunStatus::Running->value,
                'input_fingerprint' => $fingerprint,
                'input_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'started_at' => now(),
                'updated_at' => now(),
            ]) > 0;
    }

    public function reportProgress(int $schoolId, int $runId, array $progress): void
    {
        $this->bindSchool($schoolId);
        DB::table($this->table())->where('school_id', $schoolId)->where('id', $runId)
            ->update(['progress' => json_encode($progress, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
    }

    public function cancelRequested(int $schoolId, int $runId): bool
    {
        $this->bindSchool($schoolId);

        return (bool) DB::table($this->table())->where('school_id', $schoolId)->where('id', $runId)->value('cancel_requested');
    }

    public function finish(int $schoolId, int $runId, int $status, array $outcome): void
    {
        $this->bindSchool($schoolId);
        DB::table($this->table())->where('school_id', $schoolId)->where('id', $runId)->update([
            'status' => $status,
            'result' => json_encode($outcome['result'], JSON_THROW_ON_ERROR),
            'quality' => json_encode($outcome['quality'], JSON_THROW_ON_ERROR),
            'hard_violations' => $outcome['hard_violations'],
            'soft_penalty' => $outcome['soft_penalty'],
            'activities_total' => $outcome['activities_total'],
            'placed' => $outcome['placed'],
            'unplaced' => $outcome['unplaced'],
            'finished_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function fail(int $schoolId, int $runId, string $error): void
    {
        $this->bindSchool($schoolId);
        DB::table($this->table())->where('school_id', $schoolId)->where('id', $runId)
            ->whereIn('status', [GenerationRunStatus::Queued->value, GenerationRunStatus::Running->value])
            ->update(['status' => GenerationRunStatus::Failed->value, 'error' => mb_substr($error, 0, 2000), 'finished_at' => now(), 'updated_at' => now()]);
    }

    public function requestCancel(int $schoolId, int $runId): bool
    {
        $this->bindSchool($schoolId);
        $table = $this->table();
        $queued = DB::table($table)->where('school_id', $schoolId)->where('id', $runId)->where('status', GenerationRunStatus::Queued->value)
            ->update(['status' => GenerationRunStatus::Cancelled->value, 'cancel_requested' => true, 'finished_at' => now(), 'updated_at' => now()]);
        if ($queued > 0) {
            return true;
        }

        return DB::table($table)->where('school_id', $schoolId)->where('id', $runId)->where('status', GenerationRunStatus::Running->value)
            ->update(['cancel_requested' => true, 'updated_at' => now()]) > 0;
    }

    public function transition(int $schoolId, int $runId, int $from, int $to, ?int $userId = null): bool
    {
        $this->bindSchool($schoolId);
        $values = ['status' => $to, 'updated_at' => now()];
        if ($to === GenerationRunStatus::Applied->value) {
            $values['applied_at'] = now();
            $values['applied_by'] = $userId;
        }

        return DB::table($this->table())->where('school_id', $schoolId)->where('id', $runId)->where('status', $from)->update($values) > 0;
    }

    private function map(object $r): array
    {
        $json = static fn ($v): ?array => $v === null ? null : (array) json_decode((string) $v, true);

        return [
            'id' => (int) $r->id,
            'academic_year_id' => (int) $r->academic_year_id,
            'mode' => (int) $r->mode,
            'status' => (int) $r->status,
            'is_what_if' => (bool) $r->is_what_if,
            'scope' => $json($r->scope) ?? [],
            'options' => $json($r->options) ?? [],
            'solver' => (string) $r->solver,
            'progress' => $json($r->progress),
            'cancel_requested' => (bool) $r->cancel_requested,
            'quality' => $json($r->quality),
            'hard_violations' => $r->hard_violations !== null ? (int) $r->hard_violations : null,
            'soft_penalty' => $r->soft_penalty !== null ? (int) $r->soft_penalty : null,
            'activities_total' => $r->activities_total !== null ? (int) $r->activities_total : null,
            'placed' => $r->placed !== null ? (int) $r->placed : null,
            'unplaced' => $r->unplaced !== null ? (int) $r->unplaced : null,
            'error' => $r->error,
            'requested_by' => $r->requested_by !== null ? (int) $r->requested_by : null,
            'applied_by' => $r->applied_by !== null ? (int) $r->applied_by : null,
            'started_at' => $r->started_at,
            'finished_at' => $r->finished_at,
            'applied_at' => $r->applied_at,
            'created_at' => (string) $r->created_at,
            'input_fingerprint' => $r->input_fingerprint,
            'result' => property_exists($r, 'result') ? $json($r->result) : null,
            'input_snapshot' => property_exists($r, 'input_snapshot') ? $json($r->input_snapshot) : null,
        ];
    }

    private function table(): string
    {
        return SchemaHelper::qualified('timetable', 'generation_runs');
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
