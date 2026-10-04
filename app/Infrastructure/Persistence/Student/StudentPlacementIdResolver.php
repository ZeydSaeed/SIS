<?php

namespace App\Infrastructure\Persistence\Student;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;

/**
 * Derives structured placement FKs from the admission placement text kept on
 * the student (exact names within the school — the organization catalog is
 * seeded from the same list the admission form uses).
 *
 * - department_id: department named department_name (same branch preferred);
 * - branch_id: kept when given, else taken from the resolved department;
 * - grade_level_id: grade level of the school's class named admitted_class_name.
 */
final class StudentPlacementIdResolver
{
    /**
     * @return array{branch_id: int|null, department_id: int|null, grade_level_id: int|null}
     */
    public function resolve(int $schoolId, ?int $branchId, ?string $departmentName, ?string $admittedClassName): array
    {
        $department = $this->department($schoolId, $branchId, $departmentName);

        return [
            'branch_id' => $branchId ?? $department['branch_id'],
            'department_id' => $department['id'],
            'grade_level_id' => $this->gradeLevelId($schoolId, $admittedClassName),
        ];
    }

    /**
     * @return array{id: int|null, branch_id: int|null}
     */
    private function department(int $schoolId, ?int $branchId, ?string $name): array
    {
        $name = trim((string) $name);
        if ($name === '') {
            return ['id' => null, 'branch_id' => null];
        }

        $candidates = DB::table(SchemaHelper::qualified('organization', 'departments'))
            ->where('school_id', $schoolId)
            ->when($branchId !== null, fn ($query) => $query->orderByRaw('branch_id = ? DESC NULLS LAST', [$branchId]))
            ->orderBy('id')
            ->get(['id', 'branch_id', 'name']);

        // Exact name first; then the same Arabic normalization the UI uses (equality, never "contains").
        $row = $candidates->first(fn ($item) => trim((string) $item->name) === $name)
            ?? $candidates->first(fn ($item) => self::normalize((string) $item->name) === self::normalize($name));

        return [
            'id' => $row !== null ? (int) $row->id : null,
            'branch_id' => $row !== null && $row->branch_id !== null ? (int) $row->branch_id : null,
        ];
    }

    /** Mirrors normalizeArabicLabel() in resources/js/lib/enrollment-dialog-resolve.ts. */
    private static function normalize(string $value): string
    {
        $value = str_replace(['أ', 'إ', 'آ', 'ة', 'ى', 'ئ', 'ؤ'], ['ا', 'ا', 'ا', 'ه', 'ي', 'ي', 'و'], trim($value));
        $value = (string) preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $value);
        $value = (string) preg_replace('/^(ال)+/u', '', $value);

        return mb_strtolower((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function gradeLevelId(int $schoolId, ?string $className): ?int
    {
        $name = trim((string) $className);
        if ($name === '') {
            return null;
        }

        if (SchemaHelper::isPostgreSql()) {
            DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
        }
        $gradeLevelId = DB::table(SchemaHelper::qualified('enrollment', 'classes'))
            ->where('school_id', $schoolId)
            ->where('name', $name)
            ->orderByDesc('academic_year_id')
            ->value('grade_level_id');

        return $gradeLevelId !== null ? (int) $gradeLevelId : null;
    }
}
