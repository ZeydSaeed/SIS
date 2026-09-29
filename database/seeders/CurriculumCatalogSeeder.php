<?php

namespace Database\Seeders;

use App\Database\SchemaHelper;
use Database\Seeders\Support\AdmissionCatalogReference;
use Database\Seeders\Support\CurriculumSubjectCatalogReference;
use Database\Seeders\Support\FoundationReference;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds global curriculum.subjects + school-scoped vocational.specializations
 * (1:1 with departments) and specialization_subjects from the curriculum SSOT.
 */
class CurriculumCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $subjectIdsByName = $this->upsertSubjects($now);

        $schoolId = DB::table(SchemaHelper::qualified('organization', 'schools'))
            ->where('code', FoundationReference::SCHOOL_CODE)
            ->value('id');

        if ($schoolId === null) {
            return;
        }

        $this->upsertSpecializationsAndLinks((int) $schoolId, $subjectIdsByName, $now);
    }

    /**
     * @return array<string, int>
     */
    private function upsertSubjects(mixed $now): array
    {
        $table = SchemaHelper::qualified('curriculum', 'subjects');
        $ids = [];

        foreach (CurriculumSubjectCatalogReference::uniqueSubjects() as $subject) {
            $code = CurriculumSubjectCatalogReference::subjectCode($subject['name']);
            $existing = DB::table($table)->where('code', $code)->first(['id']);

            if ($existing !== null) {
                DB::table($table)->where('id', (int) $existing->id)->update([
                    'name' => $subject['name'],
                    'subject_type' => $subject['subject_type'],
                    'credit_hours' => $subject['credit_hours'],
                    'max_grade' => 100,
                    'pass_grade' => 50,
                    'status' => 1,
                    'updated_at' => $now,
                ]);
                $ids[$subject['name']] = (int) $existing->id;

                continue;
            }

            $ids[$subject['name']] = (int) DB::table($table)->insertGetId([
                'code' => $code,
                'name' => $subject['name'],
                'name_en' => null,
                'subject_type' => $subject['subject_type'],
                'credit_hours' => $subject['credit_hours'],
                'max_grade' => 100,
                'pass_grade' => 50,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $ids;
    }

    /**
     * @param  array<string, int>  $subjectIdsByName
     */
    private function upsertSpecializationsAndLinks(int $schoolId, array $subjectIdsByName, mixed $now): void
    {
        $branchesTable = SchemaHelper::qualified('organization', 'branches');
        $departmentsTable = SchemaHelper::qualified('organization', 'departments');
        $specsTable = SchemaHelper::qualified('vocational', 'specializations');
        $linksTable = SchemaHelper::qualified('vocational', 'specialization_subjects');

        $branches = DB::table($branchesTable)
            ->where('school_id', $schoolId)
            ->where('status', 1)
            ->get(['id', 'name']);

        $branchIdByName = [];
        foreach ($branches as $branch) {
            $branchIdByName[(string) $branch->name] = (int) $branch->id;
        }

        foreach (AdmissionCatalogReference::DEPARTMENTS_BY_BRANCH as $branchName => $departments) {
            $branchId = $branchIdByName[$branchName] ?? null;
            if ($branchId === null) {
                continue;
            }

            foreach ($departments as $departmentName) {
                $department = DB::table($departmentsTable)
                    ->where('school_id', $schoolId)
                    ->where('branch_id', $branchId)
                    ->where('name', $departmentName)
                    ->where('status', 1)
                    ->orderBy('id')
                    ->first(['id', 'code']);

                if ($department === null) {
                    continue;
                }

                $specCode = 'SPC-'.strtoupper(substr(md5($branchName.'|'.$departmentName), 0, 8));
                $existing = DB::table($specsTable)
                    ->where('school_id', $schoolId)
                    ->where('code', $specCode)
                    ->first(['id']);

                if ($existing !== null) {
                    $specId = (int) $existing->id;
                    DB::table($specsTable)->where('id', $specId)->update([
                        'department_id' => (int) $department->id,
                        'name' => $departmentName,
                        'description' => $branchName.' / '.$departmentName,
                        'status' => 1,
                        'updated_at' => $now,
                    ]);
                } else {
                    $byName = DB::table($specsTable)
                        ->where('school_id', $schoolId)
                        ->where('name', $departmentName)
                        ->where('department_id', (int) $department->id)
                        ->first(['id']);

                    if ($byName !== null) {
                        $specId = (int) $byName->id;
                        DB::table($specsTable)->where('id', $specId)->update([
                            'code' => $specCode,
                            'status' => 1,
                            'updated_at' => $now,
                        ]);
                    } else {
                        $specId = (int) DB::table($specsTable)->insertGetId([
                            'school_id' => $schoolId,
                            'department_id' => (int) $department->id,
                            'code' => $specCode,
                            'name' => $departmentName,
                            'description' => $branchName.' / '.$departmentName,
                            'status' => 1,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }

                foreach (CurriculumSubjectCatalogReference::subjectsFor($branchName, $departmentName) as $subject) {
                    $subjectId = $subjectIdsByName[$subject['name']] ?? null;
                    if ($subjectId === null) {
                        continue;
                    }

                    $link = DB::table($linksTable)
                        ->where('specialization_id', $specId)
                        ->where('subject_id', $subjectId)
                        ->first(['id']);

                    if ($link === null) {
                        DB::table($linksTable)->insert([
                            'specialization_id' => $specId,
                            'subject_id' => $subjectId,
                            'is_required' => true,
                            'credit_hours' => $subject['credit_hours'],
                            'status' => 1,
                        ]);
                    } else {
                        DB::table($linksTable)->where('id', (int) $link->id)->update([
                            'is_required' => true,
                            'credit_hours' => $subject['credit_hours'],
                            'status' => 1,
                        ]);
                    }
                }
            }
        }
    }
}
