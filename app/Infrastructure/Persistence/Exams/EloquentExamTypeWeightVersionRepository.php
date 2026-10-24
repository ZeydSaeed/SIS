<?php

namespace App\Infrastructure\Persistence\Exams;

use App\Database\SchemaHelper;
use App\Domain\Exams\Repositories\ExamTypeWeightVersionRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class EloquentExamTypeWeightVersionRepository implements ExamTypeWeightVersionRepositoryInterface
{
    public function yearExists(int $academicYearId): bool
    {
        return DB::table(SchemaHelper::qualified('academic', 'academic_years'))->where('id', $academicYearId)->exists();
    }

    public function nextVersion(int $schoolId, int $examTypeId, int $academicYearId): int
    {
        return 1 + (int) DB::table($this->table())->where('school_id', $schoolId)->where('exam_type_id', $examTypeId)
            ->where('academic_year_id', $academicYearId)->max('version_no');
    }

    public function insert(int $schoolId, int $examTypeId, int $academicYearId, int $versionNo, int $weightPercentage, string $reason, ?int $createdBy): int
    {
        return (int) DB::table($this->table())->insertGetId([
            'school_id' => $schoolId, 'exam_type_id' => $examTypeId, 'academic_year_id' => $academicYearId,
            'version_no' => $versionNo, 'weight_percentage' => $weightPercentage, 'reason' => $reason, 'created_by' => $createdBy,
        ]);
    }

    public function newestForYear(int $schoolId, int $academicYearId): array
    {
        $out = [];
        foreach (DB::table($this->table())->where('school_id', $schoolId)->where('academic_year_id', $academicYearId)
            ->orderBy('exam_type_id')->orderBy('version_no')->get(['exam_type_id', 'version_no', 'weight_percentage']) as $r) {
            $out[(int) $r->exam_type_id] = ['version' => (int) $r->version_no, 'weight_percentage' => (int) $r->weight_percentage];
        }

        return $out;
    }

    public function history(int $schoolId, int $examTypeId, int $academicYearId): array
    {
        return DB::table($this->table().' as v')->leftJoin('public.users as u', 'u.id', '=', 'v.created_by')
            ->where('v.school_id', $schoolId)->where('v.exam_type_id', $examTypeId)->where('v.academic_year_id', $academicYearId)
            ->orderByDesc('v.version_no')
            ->get(['v.version_no', 'v.weight_percentage', 'v.reason', 'v.created_at', 'u.name as created_by_name'])
            ->map(static fn (object $r): array => [
                'version_no' => (int) $r->version_no, 'weight_percentage' => (int) $r->weight_percentage, 'reason' => (string) $r->reason,
                'created_at' => (string) $r->created_at, 'created_by_name' => $r->created_by_name,
            ])->all();
    }

    private function table(): string
    {
        return SchemaHelper::qualified('exams', 'exam_type_weight_versions');
    }
}
