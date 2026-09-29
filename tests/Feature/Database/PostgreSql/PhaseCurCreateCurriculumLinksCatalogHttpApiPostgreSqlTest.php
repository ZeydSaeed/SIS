<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseCurCreateCurriculumLinksCatalogHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function create_curriculum_auto_links_active_specialization_subjects(): void
    {
        $schoolId = $this->createSchool('SCH-CUR-LINK', 'CUR Link School');
        $yearId = $this->createAcademicYear('AY-CUR-LINK');
        $gradeId = $this->createGradeLevel('G-CUR-LINK');

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $subjectA = (int) DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->insertGetId([
            'code' => 'SUB-LINK-A',
            'name' => 'اللغة العربية',
            'name_en' => null,
            'subject_type' => 1,
            'credit_hours' => 2,
            'max_grade' => 100,
            'pass_grade' => 50,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $subjectB = (int) DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->insertGetId([
            'code' => 'SUB-LINK-B',
            'name' => 'التدريب العملي',
            'name_en' => null,
            'subject_type' => 3,
            'credit_hours' => 4,
            'max_grade' => 100,
            'pass_grade' => 50,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $specId = (int) DB::table(SchemaHelper::qualified('vocational', 'specializations'))->insertGetId([
            'school_id' => $schoolId,
            'code' => 'SPC-LINK',
            'name' => 'ميكانيك',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table(SchemaHelper::qualified('vocational', 'specialization_subjects'))->insert([
            [
                'specialization_id' => $specId,
                'subject_id' => $subjectA,
                'is_required' => true,
                'credit_hours' => 2,
                'status' => 1,
            ],
            [
                'specialization_id' => $specId,
                'subject_id' => $subjectB,
                'is_required' => true,
                'credit_hours' => 4,
                'status' => 1,
            ],
        ]);

        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantCurriculumManager($user, $schoolId);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);

        $curriculumId = (int) $this->postJson('/api/v1/curriculum/curricula', [
            'academic_year_id' => $yearId,
            'grade_level_id' => $gradeId,
            'name' => 'ميكانيك Plan',
            'specialization_id' => $specId,
        ], ['X-Idempotency-Key' => 'cur-link-catalog'])
            ->assertCreated()
            ->json('data.curriculum_id');

        $this->assertSame(2, DB::table(SchemaHelper::qualified('curriculum', 'curriculum_subjects'))
            ->where('curriculum_id', $curriculumId)
            ->where('status', 1)
            ->count());

        $this->getJson('/api/v1/curriculum/curricula/'.$curriculumId.'/subjects')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
