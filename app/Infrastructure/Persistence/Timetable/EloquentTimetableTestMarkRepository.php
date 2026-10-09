<?php

namespace App\Infrastructure\Persistence\Timetable;

use App\Database\SchemaHelper;
use App\Domain\Timetable\Repositories\TimetableTestMarkRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class EloquentTimetableTestMarkRepository implements TimetableTestMarkRepositoryInterface
{
    private const ACTIVE = 1;

    private const CLEARED = 2;

    public function active(int $schoolId, int $academicYearId): array
    {
        $this->bindSchool($schoolId);

        return DB::table($this->table())
            ->where('school_id', $schoolId)->where('academic_year_id', $academicYearId)->where('status', self::ACTIVE)
            ->pluck('mark', 'issue_key')
            ->map(static fn ($mark): int => (int) $mark)
            ->all();
    }

    public function set(int $schoolId, int $academicYearId, string $issueKey, ?int $mark, ?string $note, ?int $userId, string $at): void
    {
        $this->bindSchool($schoolId);
        DB::table($this->table())
            ->where('school_id', $schoolId)->where('academic_year_id', $academicYearId)->where('issue_key', $issueKey)->where('status', self::ACTIVE)
            ->update(['status' => self::CLEARED, 'updated_at' => $at]);
        if ($mark === null) {
            return;
        }
        DB::table($this->table())->insert([
            'school_id' => $schoolId,
            'academic_year_id' => $academicYearId,
            'issue_key' => $issueKey,
            'mark' => $mark,
            'note' => $note,
            'status' => self::ACTIVE,
            'marked_by' => $userId,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    private function table(): string
    {
        return SchemaHelper::qualified('timetable', 'test_marks');
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
