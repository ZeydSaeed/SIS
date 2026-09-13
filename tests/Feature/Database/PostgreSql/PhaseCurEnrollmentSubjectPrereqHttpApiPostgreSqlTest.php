<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseCurEnrollmentSubjectPrereqHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function enrollment_subject_assign_enforces_prerequisites_and_soft_deactivates(): void
    {
        $schoolId = $this->createSchool('SCH-CUR5', 'CUR5 School');
        $yearId = $this->createAcademicYear('AY-CUR5');
        $class = $this->createClassForSchool($schoolId, $yearId);
        $section = $this->createSectionForClass((int) $class->id);
        $student = $this->createStudentForSchool($schoolId);

        $mathId = $this->createSubject('MATH-CUR5', 'Math');
        $algId = $this->createSubject('ALG-CUR5', 'Algebra');
        DB::table(SchemaHelper::qualified('curriculum', 'prerequisites'))->insert([
            'subject_id' => $algId,
            'prerequisite_subject_id' => $mathId,
            'status' => 1,
            'created_at' => now(),
        ]);

        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantEnrollmentManager($user, $schoolId);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);

        $enrollmentId = (int) $this->postJson('/api/v1/enrollments', [
            'student_id' => $student->id,
            'academic_year_id' => $yearId,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'effective_from' => '2026-09-01',
        ])->assertCreated()->json('data.id');

        $this->postJson('/api/v1/enrollments/'.$enrollmentId.'/subjects', [
            'subject_id' => $algId,
        ], ['X-Idempotency-Key' => 'cur5-alg-fail'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'enrollment.prerequisite_not_met');

        $mathLinkId = (int) $this->postJson('/api/v1/enrollments/'.$enrollmentId.'/subjects', [
            'subject_id' => $mathId,
        ], ['X-Idempotency-Key' => 'cur5-math'])
            ->assertCreated()
            ->json('data.link_id');

        $this->postJson('/api/v1/enrollments/'.$enrollmentId.'/subjects', [
            'subject_id' => $mathId,
        ], ['X-Idempotency-Key' => 'cur5-math'])
            ->assertOk()
            ->assertJsonPath('data.from_idempotency', true);

        $algLinkId = (int) $this->postJson('/api/v1/enrollments/'.$enrollmentId.'/subjects', [
            'subject_id' => $algId,
        ], ['X-Idempotency-Key' => 'cur5-alg-ok'])
            ->assertCreated()
            ->json('data.link_id');

        $this->getJson('/api/v1/enrollments/'.$enrollmentId.'/subjects')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->postJson('/api/v1/enrollment-subjects/'.$mathLinkId.'/deactivate', [], [
            'X-Idempotency-Key' => 'cur5-deact-math',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 2);

        $this->assertDatabaseHas(SchemaHelper::qualified('enrollment', 'enrollment_subjects'), [
            'id' => $mathLinkId,
            'status' => 2,
        ]);
        $this->assertDatabaseHas(SchemaHelper::qualified('enrollment', 'enrollment_subjects'), [
            'id' => $algLinkId,
            'status' => 1,
        ]);

        $this->getJson('/api/v1/enrollments/'.$enrollmentId.'/subjects')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject_id', $algId);
    }

    private function createSubject(string $code, string $name): int
    {
        return (int) DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->insertGetId([
            'code' => $code,
            'name' => $name,
            'name_en' => $name,
            'subject_type' => 1,
            'credit_hours' => 3,
            'max_grade' => 100,
            'pass_grade' => 50,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
