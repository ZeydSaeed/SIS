<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Contracts\SafeDeleter;
use App\Database\SchemaHelper;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/** «حذف آمن» batch 4 — holidays, leave types, admission periods, exams. */
final class SafeDeleteCalendarLeaveAdmissionExamsPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function a_school_holiday_is_deleted_from_the_page(): void
    {
        $this->withoutVite();
        $schoolId = $this->createSchool('SCH-SD4', 'Calendar delete');
        $yearId = $this->createAcademicYear('AY-SD4');
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantSchoolManager($user, $schoolId);
        $this->actingAs($user)->withSession(['current_school_id' => $schoolId]);
        $holiday = (int) DB::table('academic.holidays')->insertGetId(['academic_year_id' => $yearId, 'school_id' => $schoolId, 'name' => 'Day off', 'start_date' => '2026-10-01', 'end_date' => '2026-10-01', 'holiday_type' => 1, 'status' => 1]);
        $shared = (int) DB::table('academic.holidays')->insertGetId(['academic_year_id' => $yearId, 'school_id' => null, 'name' => 'National', 'start_date' => '2026-10-02', 'end_date' => '2026-10-02', 'holiday_type' => 1, 'status' => 1]);

        $this->withHeader('X-Idempotency-Key', 'sd4-h')->from('/leave/holidays')->post('/leave/holidays/'.$holiday.'/delete')
            ->assertSessionHas('success', 'flash.holidays.deleted');
        $this->assertFalse(DB::table('academic.holidays')->where('id', $holiday)->exists());
        // A shared (ministry) holiday is not this school's to delete.
        $this->withHeader('X-Idempotency-Key', 'sd4-g')->from('/leave/holidays')->post('/leave/holidays/'.$shared.'/delete')
            ->assertSessionHasErrors(['holiday' => 'delete.not_found']);
    }

    #[Test]
    public function leave_type_admission_period_and_exam_follow_the_safe_delete_rules(): void
    {
        $schoolId = $this->createSchool('SCH-SD4B', 'Leave / admission / exams');
        $yearId = $this->createAcademicYear('AY-SD4B');
        $deleter = app(SafeDeleter::class);

        // Leave type with a policy version: both go.
        $type = (int) DB::table('leave.types')->insertGetId(['school_id' => $schoolId, 'code' => 'SD4', 'name_ar' => 'اختبار', 'applies_to' => 3, 'unit' => 1, 'is_sensitive' => false, 'status' => 1]);
        DB::table('leave.policies')->insert(['school_id' => $schoolId, 'leave_type_id' => $type, 'version_no' => 1, 'day_basis' => 1, 'requires_document' => false, 'deducts_balance' => false, 'approval_levels' => 1, 'effective_from' => '2026-09-01']);
        $this->assertSame('deleted', $deleter->delete('leave_type', $schoolId, $type)['status']);
        $this->assertFalse(DB::table('leave.policies')->where('leave_type_id', $type)->exists());

        // Admission period: deleted when no application uses it.
        $period = (int) DB::table('admission.application_periods')->insertGetId(['academic_year_id' => $yearId, 'school_id' => $schoolId, 'name' => 'P', 'start_date' => '2026-07-01', 'end_date' => '2026-08-01', 'status' => 1]);
        $this->assertSame('deleted', $deleter->delete('admission_period', $schoolId, $period)['status']);

        // Exam with a session: both go; another school's exam is not found.
        $gradeLevel = $this->createGradeLevel('G-SD4');
        $subject = (int) DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->insertGetId(['code' => 'SD4-S', 'name' => 'S', 'subject_type' => 1, 'max_grade' => 100, 'pass_grade' => 50, 'status' => 1]);
        $examType = (int) DB::table('exams.exam_types')->insertGetId(['school_id' => $schoolId, 'code' => 'SD4-T', 'name' => 'Monthly', 'weight_percentage' => 20]);
        $term = (int) DB::table('academic.terms')->insertGetId(['academic_year_id' => $yearId, 'code' => 'SD4-1', 'name' => 'Term 1', 'start_date' => '2026-09-01', 'end_date' => '2027-01-31', 'term_order' => 1]);
        $exam = (int) DB::table('exams.exams')->insertGetId(['academic_year_id' => $yearId, 'school_id' => $schoolId, 'term_id' => $term, 'exam_type_id' => $examType, 'name' => 'E', 'start_date' => '2026-11-01', 'end_date' => '2026-11-05', 'status' => 1]);
        $session = (int) DB::table('exams.exam_sessions')->insertGetId(['exam_id' => $exam, 'school_id' => $schoolId, 'subject_id' => $subject, 'session_date' => '2026-11-02', 'start_time' => '08:00', 'end_time' => '10:00', 'max_grade' => 100, 'pass_grade' => 50, 'status' => 1]);
        $this->assertSame('not_found', $deleter->delete('exam', $schoolId + 999, $exam)['status']);
        $this->assertSame('deleted', $deleter->delete('exam', $schoolId, $exam)['status']);
        $this->assertFalse(DB::table('exams.exam_sessions')->where('id', $session)->exists());
        unset($gradeLevel);
    }
}
