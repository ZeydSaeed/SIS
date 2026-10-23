<?php

namespace App\Infrastructure\Persistence\Exams;

use App\Database\SchemaHelper;
use App\Domain\Exams\Data\ExamTypeSnapshot;
use App\Domain\Exams\Repositories\ExamTypeRepositoryInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentExamTypeRepository implements ExamTypeRepositoryInterface
{
    public function listForSchool(int $schoolId): array
    {
        return $this->query($schoolId)
            ->orderBy('t.name')->orderBy('t.id')
            ->get()
            ->map(fn (object $r): ExamTypeSnapshot => $this->snapshot($r))
            ->all();
    }

    public function lockForSchool(int $examTypeId, int $schoolId): ?ExamTypeSnapshot
    {
        DB::table(SchemaHelper::qualified('exams', 'exam_types'))
            ->where('id', $examTypeId)->where('school_id', $schoolId)
            ->lockForUpdate()->value('id');
        $r = $this->query($schoolId)->where('t.id', $examTypeId)->first();

        return $r === null ? null : $this->snapshot($r);
    }

    public function codeTaken(int $schoolId, string $code, ?int $exceptId): bool
    {
        return DB::table(SchemaHelper::qualified('exams', 'exam_types'))
            ->where('school_id', $schoolId)->where('code', $code)
            ->when($exceptId !== null, static fn (Builder $q) => $q->where('id', '<>', $exceptId))
            ->exists();
    }

    public function insert(int $schoolId, string $code, string $name, int $weightPercentage): int
    {
        return (int) DB::table(SchemaHelper::qualified('exams', 'exam_types'))->insertGetId([
            'school_id' => $schoolId,
            'code' => $code,
            'name' => $name,
            'weight_percentage' => $weightPercentage,
        ]);
    }

    public function update(int $examTypeId, int $schoolId, string $code, string $name, int $weightPercentage): void
    {
        DB::table(SchemaHelper::qualified('exams', 'exam_types'))
            ->where('id', $examTypeId)->where('school_id', $schoolId)
            ->update(['code' => $code, 'name' => $name, 'weight_percentage' => $weightPercentage]);
    }

    private function query(int $schoolId): Builder
    {
        return DB::table(SchemaHelper::qualified('exams', 'exam_types').' as t')
            ->where('t.school_id', $schoolId)
            ->select(['t.id', 't.school_id', 't.code', 't.name', 't.weight_percentage'])
            ->selectRaw('exists (select 1 from '.SchemaHelper::qualified('exams', 'exams').' e where e.exam_type_id = t.id and e.school_id = t.school_id) as in_use');
    }

    private function snapshot(object $r): ExamTypeSnapshot
    {
        return new ExamTypeSnapshot(
            id: (int) $r->id,
            schoolId: (int) $r->school_id,
            code: (string) $r->code,
            name: (string) $r->name,
            weightPercentage: (int) $r->weight_percentage,
            inUse: (bool) $r->in_use,
        );
    }
}
