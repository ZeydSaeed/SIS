<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/** «نوع المادة» takes several values (owner, 2026-11-27): عملي + نظري, أساسي + عملي … or one. */
final class CurriculumSubjectTypesPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function a_subject_has_one_or_several_types_and_the_first_is_its_primary_type(): void
    {
        $schoolId = $this->createSchool('SCH-STY', 'Subject types');
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantCurriculumManager($user, $schoolId);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);

        // عملي + نظري (sent in any order).
        $this->from('/curriculum')->withHeader('X-Idempotency-Key', 'sty-1')
            ->post('/curriculum/subjects', ['code' => 'STY-1', 'name' => 'الرسم الهندسي', 'subject_types' => [4, 3]])
            ->assertSessionHas('success', 'flash.curriculum.subjectCreated');
        $mixed = (int) DB::table('curriculum.subjects')->where('code', 'STY-1')->value('id');
        $this->assertSame(3, (int) DB::table('curriculum.subjects')->where('id', $mixed)->value('subject_type'));
        $this->assertSame([3, 4], $this->typesOf($mixed));

        // One type the old way (subject_type) still works.
        $this->from('/curriculum')->withHeader('X-Idempotency-Key', 'sty-2')
            ->post('/curriculum/subjects', ['code' => 'STY-2', 'name' => 'اللغة العربية', 'subject_type' => 1])
            ->assertSessionHas('success', 'flash.curriculum.subjectCreated');
        $single = (int) DB::table('curriculum.subjects')->where('code', 'STY-2')->value('id');
        $this->assertSame([1], $this->typesOf($single));

        // Invalid / empty sets are refused.
        $this->from('/curriculum')->withHeader('X-Idempotency-Key', 'sty-3')
            ->post('/curriculum/subjects', ['code' => 'STY-3', 'name' => 'x', 'subject_types' => [5]])
            ->assertSessionHasErrors('subject_types.0');
        $this->from('/curriculum')->withHeader('X-Idempotency-Key', 'sty-4')
            ->post('/curriculum/subjects', ['code' => 'STY-4', 'name' => 'y', 'subject_types' => []])
            ->assertSessionHasErrors('subject_types');

        // The page lists every type; filtering by «نظري» finds the mixed subject.
        $this->actingAs($user)->withSession(['current_school_id' => $schoolId])
            ->get('/curriculum?subject_type=4')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('curriculum/index')
                ->has('subjects.data', 1)
                ->where('subjects.data.0.id', $mixed)
                ->where('subjects.data.0.subject_types', [3, 4]));

        // Editing replaces the set (and the primary type follows).
        $this->from('/curriculum')->withHeader('X-Idempotency-Key', 'sty-5')
            ->patch('/curriculum/subjects/'.$mixed, ['subject_types' => [1, 3]])
            ->assertSessionHas('success', 'flash.curriculum.subjectUpdated');
        $this->assertSame([1, 3], $this->typesOf($mixed));
        $this->assertSame(1, (int) DB::table('curriculum.subjects')->where('id', $mixed)->value('subject_type'));

        // Any writer keeps the primary type in the set (trigger), and «حذف» takes the set with the subject.
        DB::table('curriculum.subjects')->where('id', $single)->update(['subject_type' => 2]);
        $this->assertSame([1, 2], $this->typesOf($single));
        $this->from('/curriculum')->post('/curriculum/subjects/'.$mixed.'/delete')
            ->assertSessionHas('success', 'flash.curriculum.subjectDeleted');
        $this->assertSame([], $this->typesOf($mixed));
    }

    /** @return list<int> */
    private function typesOf(int $subjectId): array
    {
        return DB::table('curriculum.subject_types')->where('subject_id', $subjectId)->orderBy('subject_type')
            ->pluck('subject_type')->map(static fn ($t): int => (int) $t)->all();
    }
}
