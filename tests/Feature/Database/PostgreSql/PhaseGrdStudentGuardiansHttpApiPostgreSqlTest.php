<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseGrdStudentGuardiansHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_list_student_guardians(): void
    {
        $schoolId = $this->createSchool('SCH-GRD-U01', 'Guardians List');
        $student = $this->createStudentForSchool($schoolId);
        $guardianId = (int) DB::table(SchemaHelper::qualified('guardians', 'guardians'))->insertGetId([
            'first_name' => 'Primary',
            'last_name' => 'Guardian',
            'full_name' => 'Primary Guardian',
            'phone' => '0500000001',
            'email' => 'g1@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table(SchemaHelper::qualified('guardians', 'student_guardians'))->insert([
            'student_id' => $student->id,
            'guardian_id' => $guardianId,
            'relationship_type' => 1,
            'is_primary' => true,
            'is_emergency_contact' => true,
            'created_at' => now(),
        ]);

        $this->actingAsStudentManagerForSchool($schoolId);

        $this->getJson('/api/v1/students/'.$student->id.'/guardians')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.guardian_id', $guardianId)
            ->assertJsonPath('data.0.student_id', (int) $student->id)
            ->assertJsonPath('data.0.guardian_full_name', 'Primary Guardian')
            ->assertJsonPath('data.0.is_primary', true);
    }

    #[Test]
    public function missing_student_returns_404(): void
    {
        $schoolId = $this->createSchool('SCH-GRD-404', 'Guardians Missing');
        $this->actingAsStudentManagerForSchool($schoolId);

        $this->getJson('/api/v1/students/999999001/guardians')
            ->assertNotFound()
            ->assertJsonPath('error_code', 'student.not_found');
    }
}
