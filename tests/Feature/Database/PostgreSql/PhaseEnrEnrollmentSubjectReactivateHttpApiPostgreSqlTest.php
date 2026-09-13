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

final class PhaseEnrEnrollmentSubjectReactivateHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_reactivate_enrollment_subject_link(): void
    {
        $schoolId = $this->createSchool('SCH-ENR3', 'ENR-U03 School');
        $yearId = $this->createAcademicYear('AY-ENR3');
        $class = $this->createClassForSchool($schoolId, $yearId);
        $section = $this->createSectionForClass((int) $class->id);
        $student = $this->createStudentForSchool($schoolId);
        $subjectId = $this->createSubject('MATH-ENR3', 'Math');

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

        $linkId = (int) $this->postJson('/api/v1/enrollments/'.$enrollmentId.'/subjects', [
            'subject_id' => $subjectId,
        ], ['X-Idempotency-Key' => 'enr3-assign'])->assertCreated()->json('data.link_id');

        $this->postJson('/api/v1/enrollment-subjects/'.$linkId.'/deactivate', [], [
            'X-Idempotency-Key' => 'enr3-off',
        ])->assertOk();

        $this->postJson('/api/v1/enrollment-subjects/'.$linkId.'/reactivate', [], [
            'X-Idempotency-Key' => 'enr3-on',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 1);

        $this->assertDatabaseHas(SchemaHelper::qualified('enrollment', 'enrollment_subjects'), [
            'id' => $linkId,
            'status' => 1,
        ]);
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
