<?php

namespace Database\Seeders;

use App\Database\SchemaHelper;
use Database\Seeders\Support\FoundationReference;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FoundationAcademicSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table(SchemaHelper::qualified('academic', 'academic_years'))
            ->where('is_current', true)
            ->update(['is_current' => false]);

        $academicYearId = $this->upsertReturningId(
            'academic',
            'academic_years',
            ['code' => FoundationReference::ACADEMIC_YEAR_CODE],
            [
                'name' => 'Academic Year 2026-2027',
                'start_date' => '2026-09-01',
                'end_date' => '2027-06-30',
                'is_current' => true,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $this->upsertReturningId(
            'academic',
            'terms',
            ['academic_year_id' => $academicYearId, 'code' => FoundationReference::TERM_ONE_CODE],
            [
                'name' => 'First Term',
                'start_date' => '2026-09-01',
                'end_date' => '2026-12-31',
                'term_order' => 1,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $this->upsertReturningId(
            'academic',
            'terms',
            ['academic_year_id' => $academicYearId, 'code' => FoundationReference::TERM_TWO_CODE],
            [
                'name' => 'Second Term',
                'start_date' => '2027-01-01',
                'end_date' => '2027-06-30',
                'term_order' => 2,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        foreach (FoundationReference::GRADE_LEVELS as $gradeLevel) {
            DB::table(SchemaHelper::qualified('academic', 'grade_levels'))->updateOrInsert(
                ['code' => $gradeLevel['code']],
                [
                    'name' => $gradeLevel['name'],
                    'level_order' => $gradeLevel['level_order'],
                    'education_stage' => $gradeLevel['education_stage'],
                    'status' => 1,
                ],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $unique
     * @param  array<string, mixed>  $values
     */
    private function upsertReturningId(string $schema, string $table, array $unique, array $values): int
    {
        $qualified = SchemaHelper::qualified($schema, $table);

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
