<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Infrastructure\Jobs\ProcessOutboxJob;
use App\Infrastructure\Persistence\Outbox\EloquentOutboxRepository;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/**
 * Phase 2 workflow automation:
 * - bulk enrollment runs as one server call with per-student outcome (flash.bulkEnroll);
 * - a new enrollment receives its governing curriculum's required subjects via the outbox;
 * - "apply curriculum" adds missing required subjects without reviving deactivated links.
 */
final class PhaseWorkflowEnrollCurriculumAutomationPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function bulk_enrollment_reports_per_student_outcome_and_outbox_assigns_curriculum_subjects(): void
    {
        $schoolId = $this->createSchool('SCH-WFA1', 'WFA1 School');
        $yearId = $this->createAcademicYear('AY-WFA1');
        $class = $this->createClassForSchool($schoolId, $yearId);
        $section = $this->createSectionForClass((int) $class->id);
        $required = $this->createSubject('REQ-WFA1');
        $this->createCurriculumWithSubjects($schoolId, $yearId, (int) $class->grade_level_id, [$required]);

        $first = $this->createStudentForSchool($schoolId);
        $second = $this->createStudentForSchool($schoolId);
        $alreadyEnrolled = $this->createStudentForSchool($schoolId);
        $this->createActiveEnrollmentForSchool($schoolId, $yearId, $alreadyEnrolled);

        $this->actingAsEnrollmentManagerWeb(null, $schoolId);
        $this->post('/enrollments/bulk', [
            'student_ids' => [$first->id, $second->id, $alreadyEnrolled->id],
            'academic_year_id' => $yearId,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'effective_from' => '2026-09-01',
        ], ['X-Idempotency-Key' => 'wfa1-bulk'])
            ->assertRedirect()
            ->assertSessionHas('bulkEnroll', [
                'enrolled' => [(int) $first->id, (int) $second->id],
                'skipped' => [['student_id' => (int) $alreadyEnrolled->id, 'error_code' => 'enrollment.already_enrolled']],
            ]);

        (new ProcessOutboxJob)->handle(app(EloquentOutboxRepository::class));

        foreach ([$first, $second] as $student) {
            $enrollmentId = $this->activeEnrollmentId($schoolId, (int) $student->id, $yearId);
            $this->assertDatabaseHas(SchemaHelper::qualified('enrollment', 'enrollment_subjects'), [
                'enrollment_id' => $enrollmentId,
                'subject_id' => $required,
                'status' => 1,
                'is_elective' => false,
            ]);
        }
    }

    #[Test]
    public function apply_curriculum_adds_missing_required_subjects_but_keeps_deactivated_links(): void
    {
        $schoolId = $this->createSchool('SCH-WFA2', 'WFA2 School');
        $yearId = $this->createAcademicYear('AY-WFA2');
        $student = $this->createStudentForSchool($schoolId);
        $enrollment = $this->createActiveEnrollmentForSchool($schoolId, $yearId, $student);
        $gradeLevelId = (int) DB::table(SchemaHelper::qualified('enrollment', 'classes'))
            ->where('id', $enrollment->class_id)
            ->value('grade_level_id');

        $kept = $this->createSubject('KEEP-WFA2');
        $added = $this->createSubject('ADD-WFA2');
        $curriculumId = $this->createCurriculumWithSubjects($schoolId, $yearId, $gradeLevelId, [$kept, $added]);

        // Staff deliberately deactivated "kept" for this enrollment earlier.
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
        DB::table(SchemaHelper::qualified('enrollment', 'enrollment_subjects'))->insert([
            'enrollment_id' => $enrollment->id,
            'subject_id' => $kept,
            'is_elective' => false,
            'status' => 2,
            'created_at' => now(),
        ]);

        $this->actingAsEnrollmentManagerWeb(null, $schoolId);
        $this->post('/curriculum/curricula/'.$curriculumId.'/apply-to-enrollments')
            ->assertRedirect()
            ->assertSessionHas('success', 'flash.curriculum.appliedToEnrollmentsQueued');

        $this->assertDatabaseHas(SchemaHelper::qualified('enrollment', 'enrollment_subjects'), [
            'enrollment_id' => $enrollment->id,
            'subject_id' => $added,
            'status' => 1,
        ]);
        $this->assertDatabaseHas(SchemaHelper::qualified('enrollment', 'enrollment_subjects'), [
            'enrollment_id' => $enrollment->id,
            'subject_id' => $kept,
            'status' => 2,
        ]);
    }

    #[Test]
    public function enrollment_viewer_cannot_apply_curriculum(): void
    {
        $schoolId = $this->createSchool('SCH-WFA3', 'WFA3 School');
        $yearId = $this->createAcademicYear('AY-WFA3');
        $curriculumId = $this->createCurriculumWithSubjects($schoolId, $yearId, $this->createGradeLevel(), []);

        $viewer = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantEnrollmentViewer($viewer, $schoolId);
        $this->actingAs($viewer);
        $this->withSession(['current_school_id' => $schoolId]);

        $this->post('/curriculum/curricula/'.$curriculumId.'/apply-to-enrollments')->assertForbidden();
    }

    private function activeEnrollmentId(int $schoolId, int $studentId, int $yearId): int
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        return (int) DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))
            ->where('student_id', $studentId)
            ->where('academic_year_id', $yearId)
            ->where('status', 1)
            ->value('id');
    }

    private function createSubject(string $code): int
    {
        return (int) DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->insertGetId([
            'code' => $code,
            'name' => 'Subject '.$code,
            'name_en' => 'Subject '.$code,
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
