<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Exams\ValueObjects\GradeStatus;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseCurGradePassPrereqHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function finalized_passing_grade_satisfies_prerequisite_without_subject_history(): void
    {
        $schoolId = $this->createSchool('SCH-CUR6', 'CUR6 School');
        $yearId = $this->createAcademicYear('AY-CUR6');
        $class = $this->createClassForSchool($schoolId, $yearId);
        $section = $this->createSectionForClass((int) $class->id);
        $student = $this->createStudentForSchool($schoolId);

        $mathId = $this->createSubject('MATH-CUR6', 'Math', 50);
        $algId = $this->createSubject('ALG-CUR6', 'Algebra', 50);
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
        ], ['X-Idempotency-Key' => 'cur6-fail-none'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'enrollment.prerequisite_not_met');

        $seat = $this->seedMathExamSeat($schoolId, $yearId, $enrollmentId, (int) $student->id, $mathId);

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
        DB::table(SchemaHelper::qualified('exams', 'student_grades'))->insert([
            'academic_year_id' => $yearId,
            'school_id' => $schoolId,
            'exam_enrollment_id' => $seat['exam_enrollment_id'],
            'exam_session_id' => $seat['session_id'],
            'enrollment_id' => $enrollmentId,
            'student_id' => $student->id,
            'subject_id' => $mathId,
            'score' => 40,
            'max_score' => 100,
            'is_absent' => false,
            'status' => GradeStatus::Finalized->value,
            'is_current' => true,
            'entered_at' => now(),
            'finalized_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/v1/enrollments/'.$enrollmentId.'/subjects', [
            'subject_id' => $algId,
        ], ['X-Idempotency-Key' => 'cur6-fail-low'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'enrollment.prerequisite_not_met');

        DB::table(SchemaHelper::qualified('exams', 'student_grades'))
            ->where('exam_enrollment_id', $seat['exam_enrollment_id'])
            ->where('academic_year_id', $yearId)
            ->update(['score' => 60, 'updated_at' => now()]);

        $this->postJson('/api/v1/enrollments/'.$enrollmentId.'/subjects', [
            'subject_id' => $algId,
        ], ['X-Idempotency-Key' => 'cur6-alg-ok'])
            ->assertCreated()
            ->assertJsonPath('data.enrollment_id', $enrollmentId);

        $this->assertDatabaseMissing(SchemaHelper::qualified('enrollment', 'enrollment_subjects'), [
            'enrollment_id' => $enrollmentId,
            'subject_id' => $mathId,
        ]);
        $this->assertDatabaseHas(SchemaHelper::qualified('enrollment', 'enrollment_subjects'), [
            'enrollment_id' => $enrollmentId,
            'subject_id' => $algId,
            'status' => 1,
        ]);
    }

    /**
     * @return array{session_id:int,exam_enrollment_id:int}
     */
    private function seedMathExamSeat(
        int $schoolId,
        int $yearId,
        int $enrollmentId,
        int $studentId,
        int $subjectId,
    ): array {
        $termId = (int) DB::table(SchemaHelper::qualified('academic', 'terms'))->insertGetId([
            'academic_year_id' => $yearId,
            'code' => 'T1-CUR6',
            'name' => 'Term 1 CUR6',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-31',
            'term_order' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $typeId = (int) DB::table(SchemaHelper::qualified('exams', 'exam_types'))->insertGetId([
            'code' => 'MID-CUR6',
            'name' => 'Mid CUR6',
            'weight_percentage' => 40,
        ]);

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $examId = (int) DB::table(SchemaHelper::qualified('exams', 'exams'))->insertGetId([
            'academic_year_id' => $yearId,
            'school_id' => $schoolId,
            'term_id' => $termId,
            'exam_type_id' => $typeId,
            'name' => 'Exam CUR6',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-15',
            'status' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sessionId = (int) DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))->insertGetId([
            'exam_id' => $examId,
            'school_id' => $schoolId,
            'subject_id' => $subjectId,
            'session_date' => '2026-11-05',
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'max_grade' => 100,
            'pass_grade' => 50,
            'status' => 1,
            'created_at' => now(),
        ]);
        $examEnrollmentId = (int) DB::table(SchemaHelper::qualified('exams', 'exam_enrollments'))->insertGetId([
            'exam_session_id' => $sessionId,
            'school_id' => $schoolId,
            'enrollment_id' => $enrollmentId,
            'seat_number' => 'C61',
            'status' => 1,
            'created_at' => now(),
        ]);

        return [
            'session_id' => $sessionId,
            'exam_enrollment_id' => $examEnrollmentId,
        ];
    }

    private function createSubject(string $code, string $name, int $passGrade): int
    {
        return (int) DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->insertGetId([
            'code' => $code,
            'name' => $name,
            'name_en' => $name,
            'subject_type' => 1,
            'credit_hours' => 3,
            'max_grade' => 100,
            'pass_grade' => $passGrade,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
