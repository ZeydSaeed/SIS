<?php

namespace App\Infrastructure\Persistence\Exams;

use App\Application\Exams\Contracts\ExamFormLookupsReadRepositoryInterface;
use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;

final class EloquentExamFormLookupsReadRepository implements ExamFormLookupsReadRepositoryInterface
{
    private const ACTIVE = 1;

    public function terms(int $academicYearId): array
    {
        return DB::table(SchemaHelper::qualified('academic', 'terms'))
            ->where('academic_year_id', $academicYearId)
            ->orderBy('term_order')->orderBy('id')
            ->get(['id', 'name'])
            ->map(static fn (object $r): array => ['id' => (int) $r->id, 'name' => (string) $r->name])
            ->all();
    }

    public function examTypes(int $schoolId): array
    {
        return DB::table(SchemaHelper::qualified('exams', 'exam_types'))
            ->where('school_id', $schoolId)
            ->orderBy('name')->orderBy('id')
            ->get(['id', 'name', 'weight_percentage'])
            ->map(static fn (object $r): array => ['id' => (int) $r->id, 'name' => (string) $r->name, 'weight_percentage' => (int) $r->weight_percentage])
            ->all();
    }

    public function subjects(int $schoolId, int $academicYearId): array
    {
        return DB::table(SchemaHelper::qualified('curriculum', 'subjects').' as s')
            ->whereIn('s.id', DB::table(SchemaHelper::qualified('curriculum', 'curriculum_subjects').' as cs')
                ->join(SchemaHelper::qualified('curriculum', 'curricula').' as c', 'c.id', '=', 'cs.curriculum_id')
                ->where('c.school_id', $schoolId)->where('c.academic_year_id', $academicYearId)
                ->where('c.status', self::ACTIVE)->where('cs.status', self::ACTIVE)
                ->select('cs.subject_id'))
            ->orderBy('s.name')->orderBy('s.id')
            ->get(['s.id', 's.name'])
            ->map(static fn (object $r): array => ['id' => (int) $r->id, 'name' => (string) $r->name])
            ->all();
    }
}
