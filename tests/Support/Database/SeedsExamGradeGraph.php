<?php

namespace Tests\Support\Database;

use App\Database\StudentGradesPartitionManager;
use Illuminate\Support\Facades\DB;

/**
 * Shared seed graph for Phase 3B PostgreSQL grade tests.
 */
trait SeedsExamGradeGraph
{
    /**
     * @return array{
     *   school_a:int,school_b:int,year_a:int,year_b:int,term_a:int,term_b:int,
     *   type_id:int,subject_id:int,grade_level_id:int,
     *   enrollment_a:int,enrollment_b:int,student_a:int,student_b:int,
     *   exam_enrollment_a:int,exam_enrollment_b:int,
     *   session_a:int,session_b:int
     * }
     */
    protected function seedTwoSchoolGradeGraph(): array
    {
        $ministryId = (int) DB::table('organization.ministries')->insertGetId([
            'code' => 'MOE-G',
            'name' => 'Ministry G',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $dirId = (int) DB::table('organization.directorates')->insertGetId([
            'ministry_id' => $ministryId,
            'code' => 'DIR-G',
            'name' => 'Dir G',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $schoolA = (int) DB::table('organization.schools')->insertGetId([
            'directorate_id' => $dirId,
            'code' => 'SCH-GA',
            'name' => 'School GA',
            'school_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $schoolB = (int) DB::table('organization.schools')->insertGetId([
            'directorate_id' => $dirId,
            'code' => 'SCH-GB',
            'name' => 'School GB',
            'school_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $yearA = (int) DB::table('academic.academic_years')->insertGetId([
            'code' => 'AY-GA',
            'name' => 'Year GA',
            'start_date' => '2025-09-01',
            'end_date' => '2026-06-30',
            'is_current' => false,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $yearB = (int) DB::table('academic.academic_years')->insertGetId([
            'code' => 'AY-GB',
            'name' => 'Year GB',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'is_current' => true,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        StudentGradesPartitionManager::ensurePartitionForAcademicYear($yearA);
        StudentGradesPartitionManager::ensurePartitionForAcademicYear($yearB);

        $termA = (int) DB::table('academic.terms')->insertGetId([
            'academic_year_id' => $yearA,
            'code' => 'T1A',
            'name' => 'Term 1A',
            'start_date' => '2025-09-01',
            'end_date' => '2025-12-31',
            'term_order' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $termB = (int) DB::table('academic.terms')->insertGetId([
            'academic_year_id' => $yearB,
            'code' => 'T1B',
            'name' => 'Term 1B',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-31',
            'term_order' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $typeId = (int) DB::table('exams.exam_types')->insertGetId([
            'code' => 'MIDG',
            'name' => 'Midterm G',
            'weight_percentage' => 40,
        ]);
        $subjectId = (int) DB::table('curriculum.subjects')->insertGetId([
            'code' => 'MATHG',
            'name' => 'Math G',
            'subject_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $gradeLevelId = (int) DB::table('academic.grade_levels')->insertGetId([
            'code' => 'G10G',
            'name' => 'Grade 10G',
            'level_order' => 10,
            'education_stage' => 1,
            'status' => 1,
        ]);

        $seatA = $this->seedSchoolSeat($schoolA, $yearA, $termA, $typeId, $subjectId, $gradeLevelId, 'A');
        $seatB = $this->seedSchoolSeat($schoolB, $yearB, $termB, $typeId, $subjectId, $gradeLevelId, 'B');

        return [
            'school_a' => $schoolA,
            'school_b' => $schoolB,
            'year_a' => $yearA,
            'year_b' => $yearB,
            'term_a' => $termA,
            'term_b' => $termB,
            'type_id' => $typeId,
            'subject_id' => $subjectId,
            'grade_level_id' => $gradeLevelId,
            'enrollment_a' => $seatA['enrollment_id'],
            'enrollment_b' => $seatB['enrollment_id'],
            'student_a' => $seatA['student_id'],
            'student_b' => $seatB['student_id'],
            'exam_enrollment_a' => $seatA['exam_enrollment_id'],
            'exam_enrollment_b' => $seatB['exam_enrollment_id'],
            'session_a' => $seatA['session_id'],
            'session_b' => $seatB['session_id'],
        ];
    }

    /**
     * @return array{enrollment_id:int,student_id:int,exam_enrollment_id:int,session_id:int}
     */
    private function seedSchoolSeat(
        int $schoolId,
        int $yearId,
        int $termId,
        int $typeId,
        int $subjectId,
        int $gradeLevelId,
        string $suffix,
    ): array {
        $classId = (int) DB::table('enrollment.classes')->insertGetId([
            'school_id' => $schoolId,
            'academic_year_id' => $yearId,
            'grade_level_id' => $gradeLevelId,
            'code' => 'C-'.$suffix,
            'name' => 'Class '.$suffix,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sectionId = (int) DB::table('enrollment.sections')->insertGetId([
            'class_id' => $classId,
            'code' => 'S1',
            'name' => 'Section 1',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $studentId = (int) DB::table('students.students')->insertGetId([
            'school_id' => $schoolId,
            'student_code' => 'STU-G-'.$suffix,
            'first_name' => 'Stu',
            'last_name' => $suffix,
            'full_name' => 'Stu '.$suffix,
            'gender' => 1,
            'birth_date' => '2010-01-01',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $enrollmentId = (int) DB::table('enrollment.enrollments')->insertGetId([
            'student_id' => $studentId,
            'academic_year_id' => $yearId,
            'school_id' => $schoolId,
            'class_id' => $classId,
            'section_id' => $sectionId,
            'enrollment_number' => 'ENR-G-'.$suffix,
            'status' => 1,
            'effective_from' => '2025-09-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $examId = (int) DB::table('exams.exams')->insertGetId([
            'academic_year_id' => $yearId,
            'school_id' => $schoolId,
            'term_id' => $termId,
            'exam_type_id' => $typeId,
            'name' => 'Exam '.$suffix,
            'start_date' => '2025-11-01',
            'end_date' => '2025-11-15',
            'status' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sessionId = (int) DB::table('exams.exam_sessions')->insertGetId([
            'exam_id' => $examId,
            'school_id' => $schoolId,
            'subject_id' => $subjectId,
            'session_date' => '2025-11-05',
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'max_grade' => 100,
            'pass_grade' => 50,
            'status' => 1,
            'created_at' => now(),
        ]);
        $examEnrollmentId = (int) DB::table('exams.exam_enrollments')->insertGetId([
            'exam_session_id' => $sessionId,
            'school_id' => $schoolId,
            'enrollment_id' => $enrollmentId,
            'seat_number' => 'A1',
            'status' => 1,
            'created_at' => now(),
        ]);

        return [
            'enrollment_id' => $enrollmentId,
            'student_id' => $studentId,
            'exam_enrollment_id' => $examEnrollmentId,
            'session_id' => $sessionId,
        ];
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    protected function insertGrade(array $base, array $overrides = []): int
    {
        $payload = array_merge([
            'academic_year_id' => $base['year_id'],
            'school_id' => $base['school_id'],
            'exam_enrollment_id' => $base['exam_enrollment_id'],
            'exam_session_id' => $base['session_id'],
            'enrollment_id' => $base['enrollment_id'],
            'student_id' => $base['student_id'],
            'subject_id' => $base['subject_id'],
            'score' => 70,
            'max_score' => 100,
            'is_absent' => false,
            'status' => 2,
            'is_current' => true,
            'correction_of_grade_id' => null,
            'entered_by' => null,
            'entered_at' => now(),
            'finalized_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        return (int) DB::table('exams.student_grades')->insertGetId($payload);
    }
}
