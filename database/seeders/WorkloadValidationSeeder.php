<?php

namespace Database\Seeders;

use App\Database\SchemaHelper;
use App\Infrastructure\Persistence\Eloquent\StudentRecord;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkloadValidationSeeder extends Seeder
{
    public function run(): void
    {
        $schoolId = $this->ensureWorkloadSchool();

        $count = (int) (config('sis.workload_validation._seed_count')
            ?? config('sis.workload_validation.profiles.student_search.seed_students', 50));
        $firstNames = ['Ali', 'Sara', 'Omar', 'Noor', 'Hassan', 'Layla', 'Youssef', 'Maha', 'Khalid', 'Rana'];
        $lastNames = ['Karim', 'Adil', 'Saleh', 'Fahad', 'Nasser', 'Haddad', 'Mansour', 'Aziz', 'Hamid', 'Salem'];

        for ($index = 1; $index <= $count; $index++) {
            $firstName = $firstNames[($index - 1) % count($firstNames)];
            $lastName = $lastNames[intdiv($index - 1, count($firstNames)) % count($lastNames)];
            $fullName = trim($firstName.' '.$lastName);
            $studentCode = sprintf('STU-WLV-%04d', $index);

            $existing = StudentRecord::query()->where('student_code', $studentCode)->first();
            if ($existing !== null) {
                $existing->forceFill([
                    'school_id' => $schoolId,
                    'public_id' => $existing->public_id ?? (string) Str::uuid(),
                    'national_id' => sprintf('NAT-WLV-%05d', $index),
                    'first_name' => $firstName,
                    'middle_name' => null,
                    'last_name' => $lastName,
                    'full_name' => $fullName,
                    'gender' => $index % 2 === 0 ? 2 : 1,
                    'birth_date' => sprintf('2010-%02d-15', ($index % 12) + 1),
                    'status' => 1,
                ]);
                $existing->save();

                continue;
            }

            $record = new StudentRecord;
            $record->forceFill([
                'school_id' => $schoolId,
                'student_code' => $studentCode,
                'public_id' => (string) Str::uuid(),
                'national_id' => sprintf('NAT-WLV-%05d', $index),
                'first_name' => $firstName,
                'middle_name' => null,
                'last_name' => $lastName,
                'full_name' => $fullName,
                'gender' => $index % 2 === 0 ? 2 : 1,
                'birth_date' => sprintf('2010-%02d-15', ($index % 12) + 1),
                'status' => 1,
            ]);
            $record->save();
        }
    }

    public function ensureWorkloadSchool(): int
    {
        $schoolsTable = SchemaHelper::qualified('organization', 'schools');
        $existing = DB::table($schoolsTable)->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        $ministriesTable = SchemaHelper::qualified('organization', 'ministries');
        $directoratesTable = SchemaHelper::qualified('organization', 'directorates');

        $ministryId = DB::table($ministriesTable)->insertGetId([
            'code' => 'MIN-WLV',
            'name' => 'Workload Ministry',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $directorateId = DB::table($directoratesTable)->insertGetId([
            'code' => 'DIR-WLV',
            'ministry_id' => $ministryId,
            'name' => 'Workload Directorate',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table($schoolsTable)->insertGetId([
            'code' => 'SCHOOL-WLV',
            'directorate_id' => $directorateId,
            'name' => 'Workload Validation School',
            'school_type' => 2,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
