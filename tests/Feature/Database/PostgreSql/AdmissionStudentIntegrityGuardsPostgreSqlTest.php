<?php

namespace Tests\Feature\Database\PostgreSql;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/**
 * Triggers from 2026_10_06_150000_protect_student_and_admission_records.
 */
class AdmissionStudentIntegrityGuardsPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    #[Test]
    public function students_cannot_be_hard_deleted(): void
    {
        $refs = $this->seedReferences();
        $studentId = $this->insertStudent($refs['school_id'], 'GUARD-STU-1');

        $this->assertRejected(fn () => DB::table('students.students')->where('id', $studentId)->delete());
        $this->assertSame(1, DB::table('students.students')->where('id', $studentId)->count());
    }

    #[Test]
    public function application_linked_to_a_student_cannot_be_deleted_but_an_unlinked_one_can(): void
    {
        $refs = $this->seedReferences();
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $refs['school_id']]);
        $periodId = $this->insertPeriod($refs['year_id']);
        $studentId = $this->insertStudent($refs['school_id'], 'GUARD-STU-2');

        $linked = $this->insertApplication($periodId, $refs, 'GUARD-APP-1', $studentId);
        $unlinked = $this->insertApplication($periodId, $refs, 'GUARD-APP-2', null);

        $this->assertRejected(fn () => DB::table('admission.applications')->where('id', $linked)->delete());
        $this->assertSame(1, DB::table('admission.applications')->where('id', $unlinked)->delete());
    }

    #[Test]
    public function application_period_academic_year_is_fixed_after_insert(): void
    {
        $refs = $this->seedReferences();
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $refs['school_id']]);
        $periodId = $this->insertPeriod($refs['year_id']);
        $otherYear = (int) DB::table('academic.academic_years')->insertGetId([
            'code' => 'AY-GUARD-2',
            'name' => 'Guard Year 2',
            'start_date' => '2027-09-01',
            'end_date' => '2028-06-30',
            'is_current' => false,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertRejected(
            fn () => DB::table('admission.application_periods')->where('id', $periodId)->update(['academic_year_id' => $otherYear]),
        );

        // Other columns stay editable.
        $this->assertSame(1, DB::table('admission.application_periods')->where('id', $periodId)->update(['name' => 'Renamed']));
    }

    #[Test]
    public function students_accept_every_previous_study_track_an_application_accepts(): void
    {
        $refs = $this->seedReferences();
        $studentId = $this->insertStudent($refs['school_id'], 'GUARD-STU-TRACK');

        foreach ([1, 2, 3, 4, 5, 6] as $track) {
            DB::table('students.students')->where('id', $studentId)->update(['previous_study_track' => $track]);
            $this->assertSame($track, (int) DB::table('students.students')->where('id', $studentId)->value('previous_study_track'));
        }
        try {
            DB::transaction(fn () => DB::table('students.students')->where('id', $studentId)->update(['previous_study_track' => 7]));
            $this->fail('A track outside 1–6 must violate students_previous_study_track_chk.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('students_previous_study_track_chk', $exception->getMessage());
        }
    }

    private function assertRejected(callable $write): void
    {
        $rejected = false;
        try {
            DB::transaction($write);
        } catch (QueryException $exception) {
            $rejected = str_contains($exception->getMessage(), 'cannot') || str_contains($exception->getMessage(), 'forbidden');
        }

        $this->assertTrue($rejected, 'the database trigger must reject this write');
    }

    /**
     * @return array{school_id:int,year_id:int,grade_id:int}
     */
    private function seedReferences(): array
    {
        $ministryId = (int) DB::table('organization.ministries')->insertGetId([
            'code' => 'MOE-GUARD',
            'name' => 'Guard Ministry',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $directorateId = (int) DB::table('organization.directorates')->insertGetId([
            'ministry_id' => $ministryId,
            'code' => 'DIR-GUARD',
            'name' => 'Guard Directorate',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $schoolId = (int) DB::table('organization.schools')->insertGetId([
            'directorate_id' => $directorateId,
            'code' => 'SCH-GUARD',
            'name' => 'Guard School',
            'school_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $yearId = (int) DB::table('academic.academic_years')->insertGetId([
            'code' => 'AY-GUARD',
            'name' => 'Guard Year',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'is_current' => true,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $gradeId = (int) DB::table('academic.grade_levels')->insertGetId([
            'code' => 'G-GUARD',
            'name' => 'Guard Grade',
            'level_order' => 10,
            'education_stage' => 1,
            'status' => 1,
        ]);

        return ['school_id' => $schoolId, 'year_id' => $yearId, 'grade_id' => $gradeId];
    }

    private function insertStudent(int $schoolId, string $code): int
    {
        return (int) DB::table('students.students')->insertGetId([
            'school_id' => $schoolId,
            'student_code' => $code,
            'first_name' => 'Ali',
            'last_name' => 'Guard',
            'full_name' => 'Ali Guard',
            'gender' => 1,
            'birth_date' => '2010-01-01',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertPeriod(int $yearId): int
    {
        return (int) DB::table('admission.application_periods')->insertGetId([
            'academic_year_id' => $yearId,
            'name' => 'Guard Period',
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'max_applications' => 100,
            'status' => 1,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array{school_id:int,year_id:int,grade_id:int}  $refs
     */
    private function insertApplication(int $periodId, array $refs, string $number, ?int $studentId): int
    {
        return (int) DB::table('admission.applications')->insertGetId([
            'application_period_id' => $periodId,
            'school_id' => $refs['school_id'],
            'application_number' => $number,
            'first_name' => 'Ali',
            'last_name' => 'Guard',
            'national_id' => null,
            'birth_date' => '2010-01-01',
            'gender' => 1,
            'grade_level_id' => $refs['grade_id'],
            'specialization_id' => null,
            'status' => $studentId === null ? 2 : 9,
            'student_id' => $studentId,
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
