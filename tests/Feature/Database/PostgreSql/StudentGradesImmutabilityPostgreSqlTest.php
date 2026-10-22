<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Exams\Commands\CorrectStudentGradeCommand;
use App\Application\Exams\Commands\CorrectStudentGradeHandler;
use App\Application\Exams\Commands\EnterStudentGradeCommand;
use App\Application\Exams\Commands\EnterStudentGradeHandler;
use App\Application\Exams\Commands\FinalizeStudentGradeCommand;
use App\Application\Exams\Commands\FinalizeStudentGradeHandler;
use App\Application\Exams\Commands\VoidStudentGradeCommand;
use App\Application\Exams\Commands\VoidStudentGradeHandler;
use App\Database\StudentGradesPartitionManager;
use App\Domain\Exams\Events\StudentGradeCorrected;
use App\Domain\Exams\Events\StudentGradeEntered;
use App\Domain\Exams\Events\StudentGradeFinalized;
use App\Domain\Exams\Events\StudentGradeVoided;
use App\Infrastructure\Persistence\Eloquent\OutboxMessageRecord;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Database\PostgreSqlRlsActor;
use Tests\Support\Database\SeedsExamGradeGraph;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;

/**
 * EXAMIS U5 — exams.student_grades is immutable in place: only finalize and void are allowed UPDATEs, on every year
 * partition, for every connection (superuser, replica session role, non-privileged role). Corrections stay VOID + INSERT.
 */
final class StudentGradesImmutabilityPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsApplicationExamGradeGraph;
    use SeedsExamGradeGraph;

    private const TRIGGER = 'student_grades_reject_in_place_update';

    /** @var array<string, int> */
    private array $g = [];

    /** @return array<string, int> */
    private function base(string $school = 'a'): array
    {
        $this->g = $this->g ?: $this->seedTwoSchoolGradeGraph();
        $g = $this->g;
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $g['school_'.$school]]);

        return [
            'year_id' => $g['year_'.$school], 'school_id' => $g['school_'.$school], 'exam_enrollment_id' => $g['exam_enrollment_'.$school],
            'session_id' => $g['session_'.$school], 'enrollment_id' => $g['enrollment_'.$school],
            'student_id' => $g['student_'.$school], 'subject_id' => $g['subject_id'],
        ];
    }

    /** @param  array<string, mixed>  $changes */
    private function update(int $id, int $yearId, array $changes): int
    {
        return DB::table('exams.student_grades')->where('id', $id)->where('academic_year_id', $yearId)->update($changes);
    }

    /** @param  array<string, mixed>  $changes */
    private function assertRejected(int $id, int $yearId, array $changes, string $label): void
    {
        DB::statement('SAVEPOINT immutability');
        try {
            $this->update($id, $yearId, $changes);
            $this->fail("accepted: {$label}");
        } catch (QueryException $e) {
            DB::statement('ROLLBACK TO SAVEPOINT immutability');
            $this->assertSame('23514', (string) $e->getCode(), "{$label}: ".$e->getMessage());
        }
    }

    /** @return object{status: int, is_current: bool, score: ?string, finalized_at: ?string} */
    private function row(int $id, int $yearId): object
    {
        return DB::table('exams.student_grades')->where('id', $id)->where('academic_year_id', $yearId)->first(['status', 'is_current', 'score', 'finalized_at']);
    }

    #[Test]
    public function finalize_void_and_touch_are_the_only_allowed_updates(): void
    {
        $b = $this->base();
        foreach ([1, 2, 3] as $from) {
            $id = $this->insertGrade($b, ['status' => $from, 'score' => 50 + $from]);
            $this->assertSame(1, $this->update($id, $b['year_id'], ['updated_at' => now()->addMinute()]), "touch {$from}");
            $this->assertSame(1, $this->update($id, $b['year_id'], ['status' => 4, 'finalized_at' => now(), 'updated_at' => now()]), "finalize {$from}");
            $this->assertSame(1, $this->update($id, $b['year_id'], ['updated_at' => now()->addMinutes(2)]), 'touch finalized');
            $this->assertSame(1, $this->update($id, $b['year_id'], ['status' => 5, 'is_current' => false, 'updated_at' => now()]), 'void finalized');
            $row = $this->row($id, $b['year_id']);
            $this->assertSame([5, false], [(int) $row->status, (bool) $row->is_current]);
            $this->assertNotNull($row->finalized_at, 'void keeps finalized_at');
        }

        $entered = $this->insertGrade($b, ['status' => 2]);
        $this->assertSame(1, $this->update($entered, $b['year_id'], ['status' => 5, 'is_current' => false, 'updated_at' => now()]), 'void entered');
    }

    #[Test]
    public function every_immutable_column_is_rejected(): void
    {
        $b = $this->base();
        $other = $this->base('b');
        $this->base();
        $first = $this->insertGrade($b, ['status' => 2]);
        $this->update($first, $b['year_id'], ['status' => 5, 'is_current' => false]);
        $id = $this->insertGrade($b, ['status' => 2, 'score' => 60, 'correction_of_grade_id' => $first]);

        $changes = [
            'academic_year_id (moves partition)' => ['academic_year_id' => $other['year_id']],
            'school_id' => ['school_id' => $other['school_id']],
            'exam_enrollment_id' => ['exam_enrollment_id' => $other['exam_enrollment_id']],
            'exam_session_id' => ['exam_session_id' => $other['session_id']],
            'enrollment_id' => ['enrollment_id' => $other['enrollment_id']],
            'student_id' => ['student_id' => $other['student_id']],
            'subject_id' => ['subject_id' => $b['subject_id'] + 1],
            'score' => ['score' => 61],
            'score to null (absent)' => ['score' => null, 'is_absent' => true],
            'max_score' => ['max_score' => 50],
            'is_absent' => ['is_absent' => true],
            'correction_of_grade_id to null' => ['correction_of_grade_id' => null],
            'correction_of_grade_id to another grade' => ['correction_of_grade_id' => $id],
            'entered_by (null → user)' => ['entered_by' => 1],
            'entered_at' => ['entered_at' => now()->subDay()],
            'created_at' => ['created_at' => now()->subDay()],
        ];
        foreach ($changes as $label => $change) {
            $this->assertRejected($id, $b['year_id'], $change + ['updated_at' => now()], $label);
        }
        $this->assertSame('60.00', (string) $this->row($id, $b['year_id'])->score);
    }

    #[Test]
    public function every_other_transition_is_rejected(): void
    {
        $b = $this->base();
        $y = $b['year_id'];

        $entered = $this->insertGrade($b, ['status' => 2]);
        $this->assertRejected($entered, $y, ['status' => 1], 'entered → draft');
        $this->assertRejected($entered, $y, ['status' => 3], 'entered → submitted (D1)');
        $this->assertRejected($entered, $y, ['is_current' => false], 'leave current without voiding');
        $this->assertRejected($entered, $y, ['finalized_at' => now()], 'finalized_at without finalizing');
        $this->assertRejected($entered, $y, ['status' => 4], 'finalize without finalized_at');
        $this->assertRejected($entered, $y, ['status' => 4, 'finalized_at' => now(), 'is_current' => false], 'finalize and leave current');
        $this->assertRejected($entered, $y, ['status' => 5, 'is_current' => false, 'finalized_at' => now()], 'void changing finalized_at');
        $this->update($entered, $y, ['status' => 5, 'is_current' => false]);

        $draft = $this->insertGrade($b, ['status' => 1]);
        $this->assertRejected($draft, $y, ['status' => 2], 'draft → entered (D1)');
        $this->update($draft, $y, ['status' => 4, 'finalized_at' => now()]);
        $this->assertRejected($draft, $y, ['status' => 2], 'finalized → entered');
        $this->assertRejected($draft, $y, ['status' => 1], 'finalized → draft');
        $this->assertRejected($draft, $y, ['finalized_at' => now()->addDay()], 'finalized_at changed once set');
        $this->assertRejected($draft, $y, ['finalized_at' => null], 'finalized_at cleared');
        $this->update($draft, $y, ['status' => 5, 'is_current' => false]);

        // A voided grade is terminal and never current again.
        $this->assertRejected($draft, $y, ['status' => 4], 'voided → finalized');
        $this->assertRejected($draft, $y, ['status' => 2, 'is_current' => true], 'voided → current');
        $this->assertRejected($draft, $y, ['updated_at' => now()->addDay()], 'touch a voided grade');

        // Not current but not voided (inserted directly): cannot be finalized.
        $stale = $this->insertGrade($b, ['status' => 2, 'is_current' => false]);
        $this->assertRejected($stale, $y, ['status' => 4, 'finalized_at' => now()], 'finalize a non-current grade');
    }

    #[Test]
    public function the_application_flows_still_work_and_stage_their_events(): void
    {
        $schoolId = $this->createSchool('SCH-IMM', 'Immutability');
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'IMM');
        $this->markSessionInProgressForGradeEntry($graph['session_id']);
        $year = $graph['year_id'];

        $entered = app(EnterStudentGradeHandler::class)->handle(new EnterStudentGradeCommand($schoolId, $graph['exam_enrollment_id'], '70', false, null, 'imm-enter'));
        app(FinalizeStudentGradeHandler::class)->handle(new FinalizeStudentGradeCommand($schoolId, $entered->gradeId, $year, null, 'imm-final-1'));
        $corrected = app(CorrectStudentGradeHandler::class)->handle(new CorrectStudentGradeCommand($schoolId, $entered->gradeId, $year, '75', false, 'marking error', null, 'imm-correct'));
        app(FinalizeStudentGradeHandler::class)->handle(new FinalizeStudentGradeCommand($schoolId, $corrected->newGradeId, $year, null, 'imm-final-2'));
        app(VoidStudentGradeHandler::class)->handle(new VoidStudentGradeCommand($schoolId, $corrected->newGradeId, $year, 'withdrawn sitting', null, 'imm-void'));

        $rows = DB::table('exams.student_grades')->where('exam_enrollment_id', $graph['exam_enrollment_id'])->orderBy('id')
            ->get(['id', 'score', 'status', 'is_current', 'correction_of_grade_id', 'finalized_at']);
        $this->assertCount(2, $rows);
        $this->assertSame(['70.00', 5, false], [(string) $rows[0]->score, (int) $rows[0]->status, (bool) $rows[0]->is_current]);
        $this->assertNotNull($rows[0]->finalized_at);
        $this->assertSame(['75.00', 5, false, $entered->gradeId], [(string) $rows[1]->score, (int) $rows[1]->status, (bool) $rows[1]->is_current, (int) $rows[1]->correction_of_grade_id]);

        $staged = fn (string $event): int => OutboxMessageRecord::query()->where('event_type', $event)->count();
        $this->assertSame(
            [1, 2, 1, 1],
            [$staged(StudentGradeEntered::class), $staged(StudentGradeFinalized::class), $staged(StudentGradeCorrected::class), $staged(StudentGradeVoided::class)],
        );

        // And the corrected score itself cannot be rewritten in place.
        $this->assertRejected($corrected->newGradeId, $year, ['score' => 99], 'rewrite a corrected score');
    }

    #[Test]
    public function the_trigger_is_on_every_partition_in_always_mode_including_new_years(): void
    {
        $newYear = $this->createAcademicYear('AY-IMM-NEW');
        StudentGradesPartitionManager::ensurePartitionForAcademicYear($newYear);

        $rows = DB::select("
            SELECT c.relname, t.tgenabled, t.tgparentid <> 0 AS cloned
            FROM pg_inherits i
            JOIN pg_class c ON c.oid = i.inhrelid
            LEFT JOIN pg_trigger t ON t.tgrelid = c.oid AND t.tgname = ?
            WHERE i.inhparent = 'exams.student_grades'::regclass
        ", [self::TRIGGER]);
        $parent = DB::selectOne("SELECT tgenabled FROM pg_trigger WHERE tgrelid = 'exams.student_grades'::regclass AND tgname = ?", [self::TRIGGER]);

        $this->assertNotNull($parent);
        $this->assertSame('A', $parent->tgenabled);
        $this->assertNotEmpty($rows);
        $this->assertContains(StudentGradesPartitionManager::partitionTableName($newYear), array_column($rows, 'relname'));
        foreach ($rows as $row) {
            $this->assertTrue((bool) $row->cloned, "{$row->relname} has no clone of the trigger");
            $this->assertSame('A', $row->tgenabled, "{$row->relname} is not ENABLE ALWAYS");
        }
    }

    #[Test]
    public function rows_in_two_partitions_and_a_new_partition_are_protected(): void
    {
        $a = $this->base('a');
        $idA = $this->insertGrade($a, ['score' => 40]);
        $this->assertRejected($idA, $a['year_id'], ['score' => 41], 'partition of year A');

        $b = $this->base('b');
        $idB = $this->insertGrade($b, ['score' => 40]);
        $this->assertRejected($idB, $b['year_id'], ['score' => 41], 'partition of year B');

        // A year (and partition) created after the migration.
        $newYear = $this->createAcademicYear('AY-IMM-NEW2'); // creates the year's partition (StudentGradesPartitionManager)
        $graph = $this->seedExamGradeGraph($a['school_id'], $newYear, 'NEW2');
        $idNew = $this->insertGrade([
            'year_id' => $newYear, 'school_id' => $a['school_id'], 'exam_enrollment_id' => $graph['exam_enrollment_id'],
            'session_id' => $graph['session_id'], 'enrollment_id' => $graph['enrollment_id'], 'student_id' => $graph['student_id'],
            'subject_id' => $graph['subject_id'],
        ], ['score' => 40]);
        $this->assertRejected($idNew, $newYear, ['score' => 41], 'partition created after the migration');
        $this->assertSame(1, $this->update($idNew, $newYear, ['status' => 4, 'finalized_at' => now()]), 'finalize in the new partition');
    }

    #[Test]
    public function privileged_and_unprivileged_connections_are_both_rejected(): void
    {
        $b = $this->base();
        $id = $this->insertGrade($b, ['score' => 40]);

        $this->assertTrue((bool) DB::selectOne('SELECT rolsuper FROM pg_roles WHERE rolname = current_user')->rolsuper, 'the suite connects as a superuser');
        $this->assertRejected($id, $b['year_id'], ['score' => 90], 'superuser');

        // ENABLE ALWAYS: a replica-role session (which skips ordinary triggers) is still rejected.
        DB::statement('SET LOCAL session_replication_role = replica');
        $this->assertRejected($id, $b['year_id'], ['score' => 90], 'superuser with session_replication_role = replica');
        DB::statement('SET LOCAL session_replication_role = origin');

        // Non-privileged role under RLS (the row is visible to it through the school context).
        PostgreSqlRlsActor::become();
        $this->assertSame(1, (int) DB::selectOne('SELECT count(*) AS n FROM exams.student_grades WHERE id = ?', [$id])->n);
        $this->assertRejected($id, $b['year_id'], ['score' => 90], 'non-privileged RLS role');
        $this->assertSame(1, $this->update($id, $b['year_id'], ['status' => 4, 'finalized_at' => now()]), 'finalize as the RLS role');
        PostgreSqlRlsActor::reset();

        $this->assertSame('40.00', (string) $this->row($id, $b['year_id'])->score);
    }
}
