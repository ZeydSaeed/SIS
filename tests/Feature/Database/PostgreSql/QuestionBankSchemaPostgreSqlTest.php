<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Database\PostgreSqlRlsActor;

/**
 * EXAMIS 7.3a — the database itself protects the bank: no delete, final states, frozen content after submission,
 * allowed transitions only, four eyes (CHECK), one open and one approved version per question, and RLS by school for
 * a role that is not a superuser.
 */
final class QuestionBankSchemaPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    /** @return array{school: int, other: int, subject: int, author: int, approver: int, question: int, version: int} */
    private function rows(): array
    {
        $school = $this->createSchool('SCH-BKDB', 'Bank DB');
        $other = $this->createSchool('SCH-BKDB-O', 'Bank DB other');
        $subject = (int) DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->insertGetId([
            'code' => 'BK'.substr(uniqid(), -5), 'name' => 'مادة', 'subject_type' => 1, 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $author = (int) User::factory()->create()->id;
        $approver = (int) User::factory()->create()->id;
        $question = (int) DB::table($this->questions())->insertGetId(['school_id' => $school, 'subject_id' => $subject, 'created_by' => $author]);
        $version = $this->version($question, $school, 1, $author);

        return compact('school', 'other', 'subject', 'author', 'approver', 'question', 'version');
    }

    private function version(int $question, int $school, int $no, int $author, string $status = 'draft'): int
    {
        return (int) DB::table($this->versions())->insertGetId([
            'question_id' => $question, 'school_id' => $school, 'version_no' => $no, 'type' => 'long_answer', 'stem' => 'اشرح',
            'payload' => '{"guidance": ""}', 'max_mark' => 5, 'status' => $status, 'created_by' => $author,
        ]);
    }

    private function questions(): string
    {
        return SchemaHelper::qualified('bank', 'questions');
    }

    private function versions(): string
    {
        return SchemaHelper::qualified('bank', 'question_versions');
    }

    private function submit(int $version, int $by): void
    {
        DB::table($this->versions())->where('id', $version)->update(['status' => 'in_review', 'submitted_by' => $by, 'submitted_at' => now()]);
    }

    private function approve(int $version, int $by): void
    {
        DB::table($this->versions())->where('id', $version)->update(['status' => 'approved', 'decided_by' => $by, 'decided_at' => now()]);
    }

    private function assertRefused(callable $write, string $needle): void
    {
        DB::beginTransaction();
        try {
            $write();
            $this->fail("expected the database to refuse: {$needle}");
        } catch (QueryException $e) {
            $this->assertStringContainsString($needle, $e->getMessage());
        } finally {
            DB::rollBack();
        }
    }

    #[Test]
    public function rows_are_never_deleted(): void
    {
        $r = $this->rows();

        $this->assertRefused(fn () => DB::table($this->versions())->where('id', $r['version'])->delete(), 'never deleted');
        $this->assertRefused(fn () => DB::table($this->questions())->where('id', $r['question'])->delete(), 'never deleted');
    }

    #[Test]
    public function content_freezes_after_submission_and_transitions_are_limited(): void
    {
        $r = $this->rows();
        DB::table($this->versions())->where('id', $r['version'])->update(['stem' => 'اشرح بالتفصيل']);

        $this->assertRefused(fn () => $this->approve($r['version'], $r['approver']), 'not an allowed transition');
        $this->submit($r['version'], $r['author']);
        $this->assertRefused(fn () => DB::table($this->versions())->where('id', $r['version'])->update(['stem' => 'تغيير']), 'immutable');
        $this->assertRefused(fn () => DB::table($this->versions())->where('id', $r['version'])->update(['version_no' => 9]), 'immutable');
        $this->assertRefused(fn () => DB::table($this->versions())->where('id', $r['version'])->update(['status' => 'rejected', 'decided_by' => $r['approver'], 'decided_at' => now()]), 'rejected_note');

        DB::table($this->versions())->where('id', $r['version'])->update(['status' => 'rejected', 'decided_by' => $r['approver'], 'decided_at' => now(), 'decision_note' => 'غير واضح']);
        $this->assertRefused(fn () => DB::table($this->versions())->where('id', $r['version'])->update(['decision_note' => 'تعديل']), 'final');
    }

    #[Test]
    public function the_author_cannot_approve_their_own_version(): void
    {
        $r = $this->rows();
        $this->submit($r['version'], $r['author']);

        $this->assertRefused(fn () => $this->approve($r['version'], $r['author']), 'four_eyes');
        $this->approve($r['version'], $r['approver']);
        $this->assertSame('approved', DB::table($this->versions())->where('id', $r['version'])->value('status'));
    }

    #[Test]
    public function a_question_has_one_open_and_one_approved_version(): void
    {
        $r = $this->rows();

        $this->assertRefused(fn () => $this->version($r['question'], $r['school'], 2, $r['author']), 'one_open');
        $this->submit($r['version'], $r['author']);
        $this->approve($r['version'], $r['approver']);
        $second = $this->version($r['question'], $r['school'], 2, $r['author']);
        $this->submit($second, $r['author']);
        $this->assertRefused(fn () => $this->approve($second, $r['approver']), 'one_approved');

        DB::table($this->versions())->where('id', $r['version'])->update(['status' => 'superseded']);
        $this->approve($second, $r['approver']);
        $this->assertRefused(fn () => DB::table($this->versions())->where('id', $r['version'])->update(['status' => 'approved']), 'final');
    }

    #[Test]
    public function a_retired_question_is_final_and_its_identity_is_immutable(): void
    {
        $r = $this->rows();

        $this->assertRefused(fn () => DB::table($this->questions())->where('id', $r['question'])->update(['subject_id' => $r['subject'] + 999]), 'immutable');
        $this->assertRefused(fn () => DB::table($this->questions())->where('id', $r['question'])->update(['status' => 'retired', 'retired_at' => now()]), 'questions_retired_check');
        DB::table($this->questions())->where('id', $r['question'])->update(['status' => 'retired', 'retired_at' => now(), 'retire_reason' => 'قديم']);
        $this->assertRefused(fn () => DB::table($this->questions())->where('id', $r['question'])->update(['status' => 'active', 'retired_at' => null, 'retire_reason' => null]), 'final');
    }

    #[Test]
    public function a_version_belongs_to_the_school_of_its_question(): void
    {
        $r = $this->rows();
        $this->submit($r['version'], $r['author']);
        $this->approve($r['version'], $r['approver']);

        $this->assertRefused(fn () => $this->version($r['question'], $r['other'], 2, $r['author']), 'question_versions_question_fk');
    }

    #[Test]
    public function rls_hides_other_schools_and_refuses_their_writes(): void
    {
        $r = $this->rows();
        DB::beginTransaction();
        PostgreSqlRlsActor::become();
        $set = static fn (?int $school) => DB::statement("SELECT set_config('app.current_school_id', ?, true)", [$school === null ? '' : (string) $school]);

        foreach ([$this->questions(), $this->versions()] as $table) {
            $set(null);
            $this->assertSame(0, DB::table($table)->count(), "{$table}: nothing without a school");
            $set($r['other']);
            $this->assertSame(0, DB::table($table)->count(), "{$table}: nothing of another school");
            $set($r['school']);
            $this->assertSame(1, DB::table($table)->count(), "{$table}: own school's row");
        }

        $set($r['other']);
        DB::statement('SAVEPOINT bank_rls');
        try {
            DB::table($this->questions())->insert(['school_id' => $r['school'], 'subject_id' => $r['subject'], 'created_by' => $r['author']]);
            $this->fail('a write for another school must be refused');
        } catch (QueryException $e) {
            $this->assertStringContainsString('row-level security', $e->getMessage());
            DB::statement('ROLLBACK TO SAVEPOINT bank_rls');
        }
        $this->assertSame(0, DB::table($this->versions())->where('id', $r['version'])->update(['stem' => 'x']), 'another school cannot update');

        $set($r['school']);
        DB::statement('SAVEPOINT bank_rls_delete');
        try {
            DB::table($this->versions())->where('id', $r['version'])->delete();
            $this->fail('the guard trigger still fires for this role');
        } catch (QueryException $e) {
            $this->assertStringContainsString('never deleted', $e->getMessage());
            DB::statement('ROLLBACK TO SAVEPOINT bank_rls_delete');
        }
        PostgreSqlRlsActor::reset();
        DB::rollBack();
    }
}
