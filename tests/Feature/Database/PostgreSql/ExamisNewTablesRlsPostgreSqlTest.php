<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Database\PostgreSqlRlsActor;
use Tests\Support\Results\SeedsResultSheetScene;

/**
 * EXAMIS operations — under a role that is NOT a superuser (`sis_rls_tester`, NOBYPASSRLS) the three tables added by A1/B/C
 * are invisible without a school context, invisible across schools, refuse writes for another school, and the guard
 * triggers still fire for that role.
 */
final class ExamisNewTablesRlsPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsResultSheetScene;

    private function setSchool(?int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [$schoolId === null ? '' : (string) $schoolId]);
    }

    /** @return array{school: int, other: int, pub: int, review: int, version: int, grade: int, type: int, year: int, class: int} */
    private function rows(): array
    {
        $s = $this->scene();
        $other = $this->createSchool('SCH-RLS-OTHER', 'Other school');
        $grade = (int) DB::table(SchemaHelper::qualified('exams', 'student_grades'))->where('enrollment_id', $s['final'])->where('is_current', true)->value('id');
        $type = (int) DB::table(SchemaHelper::qualified('exams', 'exam_types'))->where('school_id', $s['school'])->value('id');
        $pub = (int) DB::table(SchemaHelper::qualified('results', 'result_publications'))->insertGetId([
            'school_id' => $s['school'], 'academic_year_id' => $s['year_id'], 'class_id' => $s['class'], 'kind' => 'annual',
            'participant_count' => 1, 'source_fingerprint' => str_repeat('c', 64), 'published_snapshot' => '[]',
        ]);
        $review = (int) DB::table(SchemaHelper::qualified('exams', 'grade_reviews'))->insertGetId([
            'school_id' => $s['school'], 'academic_year_id' => $s['year_id'], 'grade_id' => $grade, 'absent_at_request' => false, 'reason' => 'سبب',
        ]);
        $version = (int) DB::table(SchemaHelper::qualified('exams', 'exam_type_weight_versions'))->insertGetId([
            'school_id' => $s['school'], 'exam_type_id' => $type, 'academic_year_id' => $s['year_id'], 'version_no' => 1, 'weight_percentage' => 100, 'reason' => 'سبب',
        ]);

        return ['school' => $s['school'], 'other' => $other, 'pub' => $pub, 'review' => $review, 'version' => $version, 'grade' => $grade, 'type' => $type, 'year' => $s['year_id'], 'class' => $s['class']];
    }

    /** @return array<string, string> table => qualified name */
    private function tables(): array
    {
        return [
            'publications' => SchemaHelper::qualified('results', 'result_publications'),
            'reviews' => SchemaHelper::qualified('exams', 'grade_reviews'),
            'versions' => SchemaHelper::qualified('exams', 'exam_type_weight_versions'),
        ];
    }

    #[Test]
    public function the_role_sees_nothing_without_a_school_and_nothing_of_another_school(): void
    {
        $r = $this->rows();
        PostgreSqlRlsActor::become();

        foreach ($this->tables() as $name => $table) {
            $this->setSchool(null);
            $this->assertSame(0, DB::table($table)->count(), "{$name}: no school context shows nothing");
            $this->setSchool($r['other']);
            $this->assertSame(0, DB::table($table)->count(), "{$name}: another school's rows are invisible");
            $this->setSchool($r['school']);
            $this->assertSame(1, DB::table($table)->count(), "{$name}: own school's row is visible");
        }
        PostgreSqlRlsActor::reset();
    }

    #[Test]
    public function the_role_cannot_write_a_row_for_another_school(): void
    {
        $r = $this->rows();
        PostgreSqlRlsActor::become();
        $this->setSchool($r['other']);

        $attempts = [
            'publications' => fn () => DB::table($this->tables()['publications'])->insert([
                'school_id' => $r['school'], 'academic_year_id' => $r['year'], 'class_id' => $r['class'], 'kind' => 'gpa',
                'participant_count' => 1, 'source_fingerprint' => str_repeat('d', 64), 'published_snapshot' => '[]']),
            'reviews' => fn () => DB::table($this->tables()['reviews'])->insert([
                'school_id' => $r['school'], 'academic_year_id' => $r['year'], 'grade_id' => $r['grade'], 'absent_at_request' => false, 'reason' => 'x']),
            'versions' => fn () => DB::table($this->tables()['versions'])->insert([
                'school_id' => $r['school'], 'exam_type_id' => $r['type'], 'academic_year_id' => $r['year'], 'version_no' => 2, 'weight_percentage' => 90, 'reason' => 'x']),
        ];
        foreach ($attempts as $name => $attempt) {
            try {
                DB::transaction($attempt);
                $this->fail("{$name}: a write for another school must be refused");
            } catch (QueryException $e) {
                $this->assertStringContainsString('row-level security', $e->getMessage(), $name);
            }
        }
        PostgreSqlRlsActor::reset();
    }

    #[Test]
    public function the_guard_triggers_still_fire_for_the_non_superuser_role(): void
    {
        $r = $this->rows();
        PostgreSqlRlsActor::become();
        $this->setSchool($r['school']);

        $changes = [
            ['publications delete', fn () => DB::table($this->tables()['publications'])->where('id', $r['pub'])->delete(), 'never deleted'],
            ['publications snapshot', fn () => DB::table($this->tables()['publications'])->where('id', $r['pub'])->update(['participant_count' => 9]), 'immutable'],
            ['reviews delete', fn () => DB::table($this->tables()['reviews'])->where('id', $r['review'])->delete(), 'never deleted'],
            ['reviews request', fn () => DB::table($this->tables()['reviews'])->where('id', $r['review'])->update(['reason' => 'تعديل']), 'immutable'],
            ['versions update', fn () => DB::table($this->tables()['versions'])->where('id', $r['version'])->update(['weight_percentage' => 10]), 'insert-only'],
            ['versions delete', fn () => DB::table($this->tables()['versions'])->where('id', $r['version'])->delete(), 'insert-only'],
        ];
        foreach ($changes as [$label, $change, $needle]) {
            try {
                DB::transaction($change);
                $this->fail("{$label}: must be refused");
            } catch (QueryException $e) {
                $this->assertStringContainsString($needle, $e->getMessage(), $label);
            }
        }
        PostgreSqlRlsActor::reset();
    }
}
