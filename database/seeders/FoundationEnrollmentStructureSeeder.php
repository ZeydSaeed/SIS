<?php

namespace Database\Seeders;

use App\Database\SchemaHelper;
use Database\Seeders\Support\AdmissionCatalogReference;
use Database\Seeders\Support\FoundationReference;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * enrollment.classes + sections aligned with SIS class/section UI SSOT.
 */
class FoundationEnrollmentStructureSeeder extends Seeder
{
    public function run(): void
    {
        $schoolId = (int) DB::table(SchemaHelper::qualified('organization', 'schools'))
            ->where('code', FoundationReference::SCHOOL_CODE)
            ->value('id');
        $academicYearId = (int) DB::table(SchemaHelper::qualified('academic', 'academic_years'))
            ->where('code', FoundationReference::ACADEMIC_YEAR_CODE)
            ->value('id');

        if ($schoolId < 1 || $academicYearId < 1) {
            throw new \RuntimeException('FoundationEnrollmentStructureSeeder requires school + academic year.');
        }

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $now = now();
        $gradeByCode = DB::table(SchemaHelper::qualified('academic', 'grade_levels'))
            ->get(['id', 'code'])
            ->keyBy('code');

        foreach (AdmissionCatalogReference::CLASSES as $classDef) {
            $grade = $gradeByCode->get($classDef['grade_code']);
            if ($grade === null) {
                throw new \RuntimeException('Missing grade level '.$classDef['grade_code']);
            }

            $classId = $this->upsertReturningId(
                SchemaHelper::qualified('enrollment', 'classes'),
                [
                    'school_id' => $schoolId,
                    'academic_year_id' => $academicYearId,
                    'code' => $classDef['code'],
                ],
                [
                    'grade_level_id' => (int) $grade->id,
                    'name' => $classDef['name'],
                    'capacity' => 50,
                    'status' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            foreach (AdmissionCatalogReference::SECTIONS as $sectionDef) {
                $this->upsertReturningId(
                    SchemaHelper::qualified('enrollment', 'sections'),
                    [
                        'class_id' => $classId,
                        'code' => $sectionDef['code'],
                    ],
                    [
                        'name' => $sectionDef['name'],
                        'capacity' => 40,
                        'status' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $unique
     * @param  array<string, mixed>  $values
     */
    private function upsertReturningId(string $qualified, array $unique, array $values): int
    {
        $existing = DB::table($qualified)->where($unique)->first();
        if ($existing !== null) {
            DB::table($qualified)->where($unique)->update(array_merge($values, [
                'updated_at' => now(),
            ]));

            return (int) $existing->id;
        }

        return (int) DB::table($qualified)->insertGetId(array_merge($unique, $values));
    }
}
