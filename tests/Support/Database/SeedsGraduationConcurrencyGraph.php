<?php

namespace Tests\Support\Database;

use Illuminate\Support\Facades\DB;
use PDO;

/**
 * Phase 3C.12B — seed + dual-connection helpers for Graduation concurrency verification.
 */
trait SeedsGraduationConcurrencyGraph
{
    /**
     * @return array{school_a:int,school_b:int,year_id:int,student_a:int,student_b:int,enrollment_a:int,enrollment_b:int,policy_id:int,policy_version_id:int}
     */
    protected function seedGraduationConcurrencyGraph(): array
    {
        // Many org/academic codes are varchar(10); keep suffix short.
        $suffix = substr(str_replace('.', '', uniqid('', true)), -6);

        $ministryId = (int) DB::table('organization.ministries')->insertGetId([
            'code' => 'M'.$suffix,
            'name' => 'Ministry C'.$suffix,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $dirId = (int) DB::table('organization.directorates')->insertGetId([
            'ministry_id' => $ministryId,
            'code' => 'D'.$suffix,
            'name' => 'Dir C'.$suffix,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $schoolA = (int) DB::table('organization.schools')->insertGetId([
            'directorate_id' => $dirId,
            'code' => 'A'.$suffix,
            'name' => 'School CA'.$suffix,
            'school_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $schoolB = (int) DB::table('organization.schools')->insertGetId([
            'directorate_id' => $dirId,
            'code' => 'B'.$suffix,
            'name' => 'School CB'.$suffix,
            'school_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $yearId = (int) DB::table('academic.academic_years')->insertGetId([
            'code' => 'Y'.$suffix,
            'name' => 'Year C'.$suffix,
            'start_date' => '2025-09-01',
            'end_date' => '2026-06-30',
            'is_current' => true,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $gradeLevelId = (int) DB::table('academic.grade_levels')->insertGetId([
            'code' => 'G'.$suffix,
            'name' => 'Grade C'.$suffix,
            'level_order' => 10,
            'education_stage' => 1,
            'status' => 1,
        ]);

        $seatA = $this->seedGraduationEnrollment($schoolA, $yearId, $gradeLevelId, 'A'.$suffix);
        $seatB = $this->seedGraduationEnrollment($schoolB, $yearId, $gradeLevelId, 'B'.$suffix);

        DB::statement("SELECT set_config('app.current_school_id', ?, false)", [(string) $schoolA]);
        $policyId = (int) DB::table('graduation.eligibility_policies')->insertGetId([
            'school_id' => $schoolA,
            'policy_code' => 'P'.$suffix,
            'name' => 'Policy '.$suffix,
            'status' => 1,
            'created_at' => now(),
        ]);
        $policyVersionId = (int) DB::table('graduation.eligibility_policy_versions')->insertGetId([
            'school_id' => $schoolA,
            'eligibility_policy_id' => $policyId,
            'version_no' => 1,
            'lifecycle_status' => 2,
            'is_current_effective' => true,
            'created_at' => now(),
        ]);

        return [
            'school_a' => $schoolA,
            'school_b' => $schoolB,
            'year_id' => $yearId,
            'student_a' => $seatA['student_id'],
            'student_b' => $seatB['student_id'],
            'enrollment_a' => $seatA['enrollment_id'],
            'enrollment_b' => $seatB['enrollment_id'],
            'policy_id' => $policyId,
            'policy_version_id' => $policyVersionId,
        ];
    }

    /**
     * @return array{student_id:int,enrollment_id:int}
     */
    private function seedGraduationEnrollment(int $schoolId, int $yearId, int $gradeLevelId, string $suffix): array
    {
        $classId = (int) DB::table('enrollment.classes')->insertGetId([
            'school_id' => $schoolId,
            'academic_year_id' => $yearId,
            'grade_level_id' => $gradeLevelId,
            'code' => 'C'.$suffix,
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
            'student_code' => 'ST'.$suffix,
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
            'enrollment_number' => 'E'.$suffix,
            'status' => 1,
            'effective_from' => '2025-09-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['student_id' => $studentId, 'enrollment_id' => $enrollmentId];
    }

    protected function openPgsqlPdo(): PDO
    {
        $cfg = config('database.connections.pgsql');

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $cfg['host'],
            $cfg['port'],
            $cfg['database'],
        );

        $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $searchPath = (string) ($cfg['search_path'] ?? 'public');
        $pdo->exec('SET search_path TO '.$searchPath);

        return $pdo;
    }

    protected function setSchoolContext(PDO $pdo, int $schoolId): void
    {
        $stmt = $pdo->prepare("SELECT set_config('app.current_school_id', ?, false)");
        $stmt->execute([(string) $schoolId]);
    }
}
