<?php

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\StudentRecord;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class WorkloadValidationSeeder extends Seeder
{
    public function run(): void
    {
        $count = (int) (config('sis.workload_validation._seed_count')
            ?? config('sis.workload_validation.profiles.student_search.seed_students', 50));
        $firstNames = ['Ali', 'Sara', 'Omar', 'Noor', 'Hassan', 'Layla', 'Youssef', 'Maha', 'Khalid', 'Rana'];
        $lastNames = ['Karim', 'Adil', 'Saleh', 'Fahad', 'Nasser', 'Haddad', 'Mansour', 'Aziz', 'Hamid', 'Salem'];

        for ($index = 1; $index <= $count; $index++) {
            $firstName = $firstNames[($index - 1) % count($firstNames)];
            $lastName = $lastNames[intdiv($index - 1, count($firstNames)) % count($lastNames)];
            $fullName = trim($firstName.' '.$lastName);

            StudentRecord::query()->updateOrCreate(
                ['student_code' => sprintf('STU-WLV-%04d', $index)],
                [
                    'public_id' => (string) Str::uuid(),
                    'national_id' => sprintf('NAT-WLV-%05d', $index),
                    'first_name' => $firstName,
                    'middle_name' => null,
                    'last_name' => $lastName,
                    'full_name' => $fullName,
                    'gender' => $index % 2 === 0 ? 2 : 1,
                    'birth_date' => sprintf('2010-%02d-15', ($index % 12) + 1),
                    'status' => 1,
                ],
            );
        }
    }
}
