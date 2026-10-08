<?php

namespace Database\Seeders;

use App\Database\SchemaHelper;
use Database\Seeders\Support\AdmissionCatalogReference;
use Database\Seeders\Support\FoundationReference;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FoundationOrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $ministryId = $this->createOnce(
            'organization',
            'ministries',
            ['code' => FoundationReference::MINISTRY_CODE],
            [
                'name' => 'وزارة التربية',
                'name_en' => 'Ministry of Education',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $directorateId = $this->createOnce(
            'organization',
            'directorates',
            ['code' => FoundationReference::DIRECTORATE_CODE],
            [
                'ministry_id' => $ministryId,
                'name' => 'مديرية التربية',
                'region' => 'كربلاء',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $schoolId = $this->createOnce(
            'organization',
            'schools',
            ['code' => FoundationReference::SCHOOL_CODE],
            [
                'directorate_id' => $directorateId,
                'name' => FoundationReference::SCHOOL_NAME,
                'school_type' => 2,
                'address' => 'كربلاء',
                'phone' => '+964-000-000-0000',
                'email' => 'school@sis.local',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        foreach (AdmissionCatalogReference::BRANCHES as $branchName) {
            $code = $branchName === 'الصناعي'
                ? FoundationReference::BRANCH_CODE
                : AdmissionCatalogReference::branchCode($branchName);

            $branchId = $this->upsertReturningId(
                'organization',
                'branches',
                ['school_id' => $schoolId, 'code' => $code],
                [
                    'name' => $branchName,
                    'address' => 'مبنى فرع '.$branchName,
                    'description' => 'فرع '.$branchName.' في '.FoundationReference::SCHOOL_NAME,
                    'status' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            foreach (AdmissionCatalogReference::DEPARTMENTS_BY_BRANCH[$branchName] ?? [] as $departmentName) {
                $deptCode = match (true) {
                    $branchName === 'الصناعي' && $departmentName === 'كهرباء' => 'DEP-ELEC',
                    $branchName === 'الصناعي' && $departmentName === 'ميكانيك' => 'DEP-MECH',
                    default => AdmissionCatalogReference::departmentCode($branchName, $departmentName),
                };

                $this->upsertReturningId(
                    'organization',
                    'departments',
                    ['school_id' => $schoolId, 'code' => $deptCode],
                    [
                        'branch_id' => $branchId,
                        'name' => $departmentName,
                        'department_type' => 1,
                        'description' => 'اختصاص '.$departmentName.' — فرع '.$branchName,
                        'status' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }
        }

        $this->seedSecondSchool($directorateId, $now);
    }

    /**
     * The second school of the demo (teachers who teach in two schools): one branch and one department,
     * named exactly like the first school's so the catalogue has a single spelling.
     */
    private function seedSecondSchool(int $directorateId, mixed $now): void
    {
        $schoolId = $this->createOnce(
            'organization',
            'schools',
            ['code' => FoundationReference::SECOND_SCHOOL_CODE],
            [
                'directorate_id' => $directorateId,
                'name' => FoundationReference::SECOND_SCHOOL_NAME,
                'school_type' => 2,
                'address' => 'كربلاء',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $branch = 'الحاسوب وتقنية المعلومات';
        $branchId = $this->upsertReturningId(
            'organization',
            'branches',
            ['school_id' => $schoolId, 'code' => AdmissionCatalogReference::branchCode($branch)],
            ['name' => $branch, 'address' => 'مبنى فرع '.$branch, 'description' => 'فرع '.$branch.' في '.FoundationReference::SECOND_SCHOOL_NAME, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
        );
        $department = 'تجميع وصيانة الحاسوب';
        $this->upsertReturningId(
            'organization',
            'departments',
            ['school_id' => $schoolId, 'code' => AdmissionCatalogReference::departmentCode($branch, $department)],
            ['branch_id' => $branchId, 'name' => $department, 'department_type' => 1, 'description' => 'اختصاص '.$department.' — فرع '.$branch, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
        );
    }

    /**
     * Creates the row once; later runs return the existing id without touching it (names are edited in the app).
     *
     * @param  array<string, mixed>  $unique
     * @param  array<string, mixed>  $values
     */
    private function createOnce(string $schema, string $table, array $unique, array $values): int
    {
        $qualified = SchemaHelper::qualified($schema, $table);
        $existing = DB::table($qualified)->where($unique)->first();

        return $existing !== null
            ? (int) $existing->id
            : (int) DB::table($qualified)->insertGetId(array_merge($unique, $values));
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
