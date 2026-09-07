<?php

namespace Database\Seeders;

use App\Database\SchemaHelper;
use Database\Seeders\Support\FoundationReference;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FoundationOrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $ministryId = $this->upsertReturningId(
            'organization',
            'ministries',
            ['code' => FoundationReference::MINISTRY_CODE],
            [
                'name' => 'Ministry of Education',
                'name_en' => 'Ministry of Education',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $directorateId = $this->upsertReturningId(
            'organization',
            'directorates',
            ['code' => FoundationReference::DIRECTORATE_CODE],
            [
                'ministry_id' => $ministryId,
                'name' => 'Demo Directorate',
                'region' => 'Demo Region',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $schoolId = $this->upsertReturningId(
            'organization',
            'schools',
            ['code' => FoundationReference::SCHOOL_CODE],
            [
                'directorate_id' => $directorateId,
                'name' => 'Demo Vocational School',
                'school_type' => 2,
                'address' => 'Demo Address',
                'phone' => '+964-000-000-0000',
                'email' => 'demo-school@sis.local',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $branchId = $this->upsertReturningId(
            'organization',
            'branches',
            ['school_id' => $schoolId, 'code' => FoundationReference::BRANCH_CODE],
            [
                'name' => 'Main Branch',
                'address' => 'Main Campus',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $this->upsertReturningId(
            'organization',
            'departments',
            ['school_id' => $schoolId, 'code' => FoundationReference::DEPARTMENT_CODE],
            [
                'branch_id' => $branchId,
                'name' => 'Vocational Department',
                'department_type' => 1,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
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
