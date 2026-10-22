<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;

/** EXAMIS U7 — the exam pages carry display names for the term, academic year and session subjects (not raw ids only). */
final class ExamPagesDisplayNamesPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsApplicationExamGradeGraph;

    /** @return array<string, mixed> */
    private function scene(): array
    {
        $school = $this->createSchool('SCH-NAMES', 'Names');
        $g = $this->seedExamGradeGraph($school, suffix: 'NAMES');
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantGradesViewer($user, $school);
        $this->actingAs($user);
        $this->withSession(['current_school_id' => $school]);

        $name = fn (string $schema, string $table, int $id): string => (string) DB::table(SchemaHelper::qualified($schema, $table))->where('id', $id)->value('name');

        return $g + [
            'term_name' => $name('academic', 'terms', $g['term_id']),
            'year_name' => $name('academic', 'academic_years', $g['year_id']),
            'subject_name' => $name('curriculum', 'subjects', $g['subject_id']),
        ];
    }

    #[Test]
    public function the_exam_list_names_each_exams_term(): void
    {
        $s = $this->scene();

        $this->get('/exams?academic_year_id='.$s['year_id'])->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('exams/index')
            ->where("names.terms.{$s['term_id']}", $s['term_name']));
    }

    #[Test]
    public function the_exam_page_names_its_year_term_and_session_subjects(): void
    {
        $s = $this->scene();

        $this->get('/exams/'.$s['exam_id'])->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('exams/show')
            ->where("names.academic_years.{$s['year_id']}", $s['year_name'])
            ->where("names.terms.{$s['term_id']}", $s['term_name'])
            ->where("names.subjects.{$s['subject_id']}", $s['subject_name']));
    }
}
