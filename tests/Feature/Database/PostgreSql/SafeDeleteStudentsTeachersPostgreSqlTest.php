<?php

namespace Tests\Feature\Database\PostgreSql;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/** «حذف آمن» — a real delete only when nothing but the record's own rows reference it. */
final class SafeDeleteStudentsTeachersPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function a_student_with_only_a_placement_is_deleted_and_one_with_finance_is_refused(): void
    {
        $this->withoutVite();
        $this->actingAsStudentManagerWeb();
        $schoolId = (int) session('current_school_id');
        $yearId = $this->createAcademicYear('AY-SD');
        $gradeId = $this->createGradeLevel('G-SD');
        $classId = (int) DB::table('enrollment.classes')->insertGetId(['school_id' => $schoolId, 'academic_year_id' => $yearId, 'grade_level_id' => $gradeId, 'code' => 'CLS-SD', 'name' => 'SD', 'status' => 1]);
        $sectionId = (int) DB::table('enrollment.sections')->insertGetId(['class_id' => $classId, 'code' => 'SEC-A', 'name' => 'A', 'status' => 1]);

        $plain = $this->createStudentForSchool($schoolId, ['student_code' => 'STU-SD-1', 'first_name' => 'A', 'last_name' => 'B', 'full_name' => 'A B']);
        $plainId = (int) (is_object($plain) ? $plain->id : $plain);
        DB::table('enrollment.enrollments')->insert(['student_id' => $plainId, 'academic_year_id' => $yearId, 'school_id' => $schoolId, 'class_id' => $classId, 'section_id' => $sectionId, 'status' => 1, 'effective_from' => '2026-09-01']);

        $this->from('/students')->post('/students/'.$plainId.'/delete')
            ->assertSessionHas('success', 'flash.studentDeleted');
        $this->assertFalse(DB::table('students.students')->where('id', $plainId)->exists());
        $this->assertFalse(DB::table('enrollment.enrollments')->where('student_id', $plainId)->exists());
        // The bypass does not outlive the safe delete (even inside the test's outer transaction).
        $this->assertSame('off', DB::selectOne("SELECT current_setting('sis.safe_delete', true) v")->v);

        $paying = $this->createStudentForSchool($schoolId, ['student_code' => 'STU-SD-2', 'first_name' => 'C', 'last_name' => 'D', 'full_name' => 'C D']);
        $payingId = (int) (is_object($paying) ? $paying->id : $paying);
        DB::table('enrollment.enrollments')->insert(['student_id' => $payingId, 'academic_year_id' => $yearId, 'school_id' => $schoolId, 'class_id' => $classId, 'section_id' => $sectionId, 'status' => 1, 'effective_from' => '2026-09-01']);
        DB::table('finance.transactions')->insert(['school_id' => $schoolId, 'student_id' => $payingId, 'academic_year_id' => $yearId, 'transaction_type' => 1, 'amount' => 10]);

        $this->from('/students')->post('/students/'.$payingId.'/delete')
            ->assertSessionHasErrors(['student' => 'delete.linked?with='.rawurlencode('السجلات المالية')]);
        // Refused as a whole: the student and the placement are both still there.
        $this->assertTrue(DB::table('students.students')->where('id', $payingId)->exists());
        $this->assertTrue(DB::table('enrollment.enrollments')->where('student_id', $payingId)->exists());

        // A plain DELETE outside the safe path is still refused by the trigger.
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('students.students')->where('id', $payingId)->delete();
    }

    #[Test]
    public function a_teacher_is_deleted_only_from_its_own_school_and_only_when_unused(): void
    {
        $this->withoutVite();
        $schoolId = $this->createSchool('SCH-TD', 'Teachers delete');
        $otherSchool = $this->createSchool('SCH-TD2', 'Other');
        $yearId = $this->createAcademicYear('AY-TD');
        $this->actingAsTeachersManagerForSchool($schoolId);
        $this->withWebSchoolContext($schoolId);
        $teacher = function (string $code, array $schools) use ($yearId): int {
            $id = (int) DB::table('teachers.teachers')->insertGetId(['employee_code' => $code, 'first_name' => 'T', 'last_name' => $code, 'full_name' => 'T '.$code, 'status' => 1]);
            foreach ($schools as $school) {
                DB::table('teachers.teacher_schools')->insert(['teacher_id' => $id, 'school_id' => $school, 'academic_year_id' => $yearId, 'is_primary' => true]);
            }

            return $id;
        };

        $free = $teacher('T-SD-1', [$schoolId]);
        $this->from('/teachers')->post('/teachers/'.$free.'/delete')->assertSessionHas('success', 'flash.teachers.deleted');
        $this->assertFalse(DB::table('teachers.teachers')->where('id', $free)->exists());

        $foreign = $teacher('T-SD-2', [$otherSchool]);
        $this->from('/teachers')->post('/teachers/'.$foreign.'/delete')->assertSessionHasErrors(['teacher' => 'delete.not_found']);

        $shared = $teacher('T-SD-3', [$schoolId, $otherSchool]);
        $this->from('/teachers')->post('/teachers/'.$shared.'/delete')
            ->assertSessionHasErrors(['teacher' => 'delete.linked?with='.rawurlencode('مدرسة أخرى ما زال يعمل فيها')]);

        $busy = $teacher('T-SD-4', [$schoolId]);
        DB::table('attendance.staff_records')->insert(['school_id' => $schoolId, 'academic_year_id' => $yearId, 'teacher_id' => $busy, 'work_date' => '2026-10-01', 'status' => 1, 'method' => 1]);
        $this->from('/teachers')->post('/teachers/'.$busy.'/delete')
            ->assertSessionHasErrors(['teacher' => 'delete.linked?with='.rawurlencode('سجلات الحضور')]);
        $this->assertTrue(DB::table('teachers.teacher_schools')->where('teacher_id', $busy)->exists());
    }
}
