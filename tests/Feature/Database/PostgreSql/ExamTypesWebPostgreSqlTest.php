<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Database\PostgreSqlRlsActor;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;

/**
 * EXAMIS U10 — exam types belong to one school: «أنواع الامتحانات» page, exam.type.manage, frozen code/weight once used,
 * school isolation (application, composite FK, RLS).
 */
final class ExamTypesWebPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsApplicationExamGradeGraph;

    private function as(string $role, int $school): User
    {
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->{'grant'.$role}($user, $school);
        $this->actingAs($user);
        $this->withSession(['current_school_id' => $school]);

        return $user;
    }

    private function types(int $school): array
    {
        return DB::table(SchemaHelper::qualified('exams', 'exam_types'))->where('school_id', $school)->orderBy('id')
            ->get(['id', 'code', 'name', 'weight_percentage'])->map(fn ($r) => (array) $r)->all();
    }

    /** @param  array<string, mixed>  $data */
    private function save(array $data, string $key, ?int $id = null): TestResponse
    {
        return $this->from('/exams/types')->post($id === null ? '/exams/types' : "/exams/types/{$id}", $data, ['X-Idempotency-Key' => $key]);
    }

    #[Test]
    public function the_manager_adds_a_type_once_per_request_and_sees_it(): void
    {
        $school = $this->createSchool('SCH-TYP', 'Types');
        $this->as('GradesManager', $school);

        $this->save(['code' => 'MID', 'name' => 'نصف السنة', 'weight_percentage' => 40], 'typ-1')
            ->assertRedirect('/exams/types')->assertSessionHas('success', 'flash.exams.typeCreated');
        $this->save(['code' => 'MID', 'name' => 'نصف السنة', 'weight_percentage' => 40], 'typ-1')->assertRedirect('/exams/types');

        $types = $this->types($school);
        $this->assertCount(1, $types);
        $this->assertSame(['code' => 'MID', 'name' => 'نصف السنة', 'weight_percentage' => 40], array_diff_key($types[0], ['id' => 1]));

        $this->get('/exams/types')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('exams/types')
            ->where('can_manage', true)
            ->where('types.data', [['id' => $types[0]['id'], 'code' => 'MID', 'name' => 'نصف السنة', 'weight_percentage' => 40, 'in_use' => false]]));
    }

    #[Test]
    public function invalid_or_duplicate_types_are_refused(): void
    {
        $school = $this->createSchool('SCH-TYP-V', 'Types V');
        $other = $this->createSchool('SCH-TYP-O', 'Types O');
        $this->as('GradesManager', $school);
        $this->save(['code' => 'FIN', 'name' => 'نهائي', 'weight_percentage' => 60], 'typ-v1')->assertSessionHas('success');

        $this->save(['code' => 'X', 'name' => 'x', 'weight_percentage' => 150], 'typ-v2')->assertSessionHasErrors('weight_percentage');
        $this->save(['code' => 'a b', 'name' => 'x', 'weight_percentage' => 10], 'typ-v3')->assertSessionHasErrors('code');
        $this->save(['code' => 'X', 'name' => 'x', 'weight_percentage' => 10, 'school_id' => $other], 'typ-v4')->assertSessionHasErrors('school_id');
        $this->save(['code' => 'X', 'name' => 'x', 'weight_percentage' => 10], '')->assertSessionHasErrors('X-Idempotency-Key');
        $this->save(['code' => 'FIN', 'name' => 'مكرر', 'weight_percentage' => 10], 'typ-v5')->assertSessionHas('error', 'exams.types.code_taken');
        $this->assertCount(1, $this->types($school));

        // The same code in another school is that school's own type.
        $this->as('GradesManager', $other);
        $this->save(['code' => 'FIN', 'name' => 'نهائي', 'weight_percentage' => 50], 'typ-v6')->assertSessionHas('success', 'flash.exams.typeCreated');
        $this->assertCount(1, $this->types($other));
    }

    #[Test]
    public function a_type_used_by_an_exam_keeps_its_code_and_weight(): void
    {
        $school = $this->createSchool('SCH-TYP-U', 'Types U');
        $g = $this->seedExamGradeGraph($school, suffix: 'TYPU');
        $typeId = (int) DB::table(SchemaHelper::qualified('exams', 'exams'))->where('id', $g['exam_id'])->value('exam_type_id');
        $code = (string) DB::table(SchemaHelper::qualified('exams', 'exam_types'))->where('id', $typeId)->value('code');
        $this->as('GradesManager', $school);

        $this->save(['code' => $code, 'name' => 'اسم آخر', 'weight_percentage' => 70], 'typ-u1', $typeId)->assertSessionHas('error', 'exams.types.locked_in_use');
        $this->save(['code' => 'NEWCODE', 'name' => 'اسم آخر', 'weight_percentage' => 40], 'typ-u2', $typeId)->assertSessionHas('error', 'exams.types.locked_in_use');
        $this->assertSame(40, (int) DB::table(SchemaHelper::qualified('exams', 'exam_types'))->where('id', $typeId)->value('weight_percentage'));

        $this->save(['code' => $code, 'name' => 'اسم آخر', 'weight_percentage' => 40], 'typ-u3', $typeId)->assertSessionHas('success', 'flash.exams.typeUpdated');
        $this->assertSame('اسم آخر', DB::table(SchemaHelper::qualified('exams', 'exam_types'))->where('id', $typeId)->value('name'));

        $this->get('/exams/types')->assertInertia(fn (Assert $page) => $page->where('types.data.0.in_use', true));
    }

    #[Test]
    public function an_unused_type_can_change_its_weight(): void
    {
        $school = $this->createSchool('SCH-TYP-W', 'Types W');
        $this->as('GradesManager', $school);
        $this->save(['code' => 'M1', 'name' => 'شهري', 'weight_percentage' => 20], 'typ-w1');
        $id = (int) $this->types($school)[0]['id'];

        $this->save(['code' => 'M1', 'name' => 'شهري', 'weight_percentage' => 25], 'typ-w2', $id)->assertSessionHas('success', 'flash.exams.typeUpdated');
        $this->assertSame(25, (int) $this->types($school)[0]['weight_percentage']);
    }

    #[Test]
    public function another_schools_type_is_invisible_and_unusable(): void
    {
        $school = $this->createSchool('SCH-TYP-A', 'Types A');
        $g = $this->seedExamGradeGraph($school, suffix: 'TYPA');
        $foreign = $this->createSchool('SCH-TYP-F', 'Types F');
        $f = $this->seedExamGradeGraph($foreign, $g['year_id'], 'TYPF');
        $foreignType = (int) DB::table(SchemaHelper::qualified('exams', 'exams'))->where('id', $f['exam_id'])->value('exam_type_id');
        $ownType = (int) DB::table(SchemaHelper::qualified('exams', 'exams'))->where('id', $g['exam_id'])->value('exam_type_id');
        $this->as('GradesManager', $school);

        $this->get('/exams/types')->assertInertia(fn (Assert $page) => $page->where('types.data', fn ($rows) => collect($rows)->pluck('id')->all() === [$ownType]));
        $this->get('/exams?academic_year_id='.$g['year_id'])->assertInertia(fn (Assert $page) => $page
            ->where('admin.exam_types', fn ($rows) => collect($rows)->pluck('id')->all() === [$ownType]));

        $this->save(['code' => 'HIJ', 'name' => 'x', 'weight_percentage' => 1], 'typ-f1', $foreignType)->assertNotFound();

        $before = DB::table(SchemaHelper::qualified('exams', 'exams'))->where('school_id', $school)->count();
        $this->from('/exams')->post('/exams', [
            'academic_year_id' => $g['year_id'], 'term_id' => $g['term_id'], 'exam_type_id' => $foreignType,
            'name' => 'بنوع مدرسة أخرى', 'start_date' => '2026-12-01', 'end_date' => '2026-12-10',
        ], ['X-Idempotency-Key' => 'typ-f2'])->assertSessionHas('error');
        $this->assertSame($before, DB::table(SchemaHelper::qualified('exams', 'exams'))->where('school_id', $school)->count());

        // The database refuses it too (composite FK exams (exam_type_id, school_id) → exam_types (id, school_id)).
        $this->expectException(QueryException::class);
        DB::table(SchemaHelper::qualified('exams', 'exams'))->insert([
            'academic_year_id' => $g['year_id'], 'school_id' => $school, 'term_id' => $g['term_id'], 'exam_type_id' => $foreignType,
            'name' => 'x', 'start_date' => '2026-12-01', 'end_date' => '2026-12-10', 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    #[Test]
    public function rls_shows_each_school_only_its_types(): void
    {
        $a = $this->createSchool('SCH-TYP-RA', 'RLS A');
        $b = $this->createSchool('SCH-TYP-RB', 'RLS B');
        $table = SchemaHelper::qualified('exams', 'exam_types');
        DB::table($table)->insert([
            ['school_id' => $a, 'code' => 'RA', 'name' => 'A', 'weight_percentage' => 10],
            ['school_id' => $b, 'code' => 'RB', 'name' => 'B', 'weight_percentage' => 10],
        ]);

        PostgreSqlRlsActor::become();
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $a]);
        $this->assertSame(['RA'], DB::table($table)->pluck('code')->all());
        DB::statement("SELECT set_config('app.current_school_id', '', true)");
        $this->assertSame(0, DB::table($table)->count());
        PostgreSqlRlsActor::reset();
    }

    #[Test]
    public function only_type_managers_change_types(): void
    {
        $school = $this->createSchool('SCH-TYP-P', 'Types P');
        foreach (['GradesTeacher', 'GradesViewer'] as $role) {
            $this->as($role, $school);
            $this->get('/exams/types')->assertOk()->assertInertia(fn (Assert $page) => $page->where('can_manage', false));
            $this->save(['code' => 'T'.$role[6], 'name' => 'x', 'weight_percentage' => 10], "typ-p-{$role}")->assertForbidden();
        }
        $this->assertSame([], $this->types($school));
    }
}
