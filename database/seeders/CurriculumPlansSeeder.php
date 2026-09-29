<?php

namespace Database\Seeders;

use App\Database\SchemaHelper;
use Database\Seeders\Support\FoundationReference;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Creates active curricula for the demo school: each specialization × each grade
 * for the current academic year, linking catalog subjects via CreateCurriculum path data.
 */
class CurriculumPlansSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $schoolId = DB::table(SchemaHelper::qualified('organization', 'schools'))
            ->where('code', FoundationReference::SCHOOL_CODE)
            ->value('id');

        if ($schoolId === null) {
            return;
        }

        $yearId = DB::table(SchemaHelper::qualified('academic', 'academic_years'))
            ->where('status', 1)
            ->orderByDesc('id')
            ->value('id');

        if ($yearId === null) {
            return;
        }

        $gradeLevels = DB::table(SchemaHelper::qualified('academic', 'grade_levels'))
            ->where('status', 1)
            ->orderBy('level_order')
            ->get(['id', 'name']);

        if ($gradeLevels->isEmpty()) {
            return;
        }

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $specializations = DB::table(SchemaHelper::qualified('vocational', 'specializations'))
            ->where('school_id', $schoolId)
            ->where('status', 1)
            ->orderBy('id')
            ->get(['id', 'name', 'department_id']);

        $branches = SchemaHelper::qualified('organization', 'branches');
        $departments = SchemaHelper::qualified('organization', 'departments');
        $curricula = SchemaHelper::qualified('curriculum', 'curricula');
        $links = SchemaHelper::qualified('curriculum', 'curriculum_subjects');
        $specSubjects = SchemaHelper::qualified('vocational', 'specialization_subjects');

        foreach ($specializations as $spec) {
            $branchName = null;
            if ($spec->department_id !== null) {
                $branchName = DB::table($departments.' as d')
                    ->leftJoin($branches.' as b', 'b.id', '=', 'd.branch_id')
                    ->where('d.id', (int) $spec->department_id)
                    ->value('b.name');
            }

            foreach ($gradeLevels as $grade) {
                $name = trim(implode(' — ', array_filter([
                    is_string($branchName) ? $branchName : null,
                    (string) $spec->name,
                    (string) $grade->name,
                ])));

                $existing = DB::table($curricula)
                    ->where('school_id', $schoolId)
                    ->where('academic_year_id', (int) $yearId)
                    ->where('grade_level_id', (int) $grade->id)
                    ->where('specialization_id', (int) $spec->id)
                    ->where('status', 1)
                    ->first(['id']);

                if ($existing !== null) {
                    $curriculumId = (int) $existing->id;
                } else {
                    $curriculumId = (int) DB::table($curricula)->insertGetId([
                        'school_id' => $schoolId,
                        'academic_year_id' => (int) $yearId,
                        'grade_level_id' => (int) $grade->id,
                        'specialization_id' => (int) $spec->id,
                        'name' => $name,
                        'status' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $templates = DB::table($specSubjects)
                    ->where('specialization_id', (int) $spec->id)
                    ->where('status', 1)
                    ->orderBy('id')
                    ->get(['subject_id', 'credit_hours', 'is_required']);

                $order = 0;
                foreach ($templates as $template) {
                    $link = DB::table($links)
                        ->where('curriculum_id', $curriculumId)
                        ->where('subject_id', (int) $template->subject_id)
                        ->first(['id']);

                    if ($link === null) {
                        DB::table($links)->insert([
                            'curriculum_id' => $curriculumId,
                            'subject_id' => (int) $template->subject_id,
                            'weekly_hours' => $template->credit_hours !== null
                                ? (int) $template->credit_hours
                                : null,
                            'is_required' => (bool) $template->is_required,
                            'subject_order' => $order,
                            'status' => 1,
                            'created_at' => $now,
                        ]);
                    }

                    $order++;
                }
            }
        }
    }
}
