<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/**
 * «متابعة طلبات التقديم» › one application: shown for any of the user's schools (read in that school's context),
 * with its status history and allowed transitions; another school's application is not found.
 */
final class AdmissionFollowUpApplicationPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function an_application_of_another_linked_school_shows_its_data_history_and_transitions(): void
    {
        [$schoolA, $schoolB, $yearId] = $this->twoSchoolsAndYear('FUA');
        $this->actingAsManagerOf([$schoolA, $schoolB], $schoolA);
        $periodId = $this->openPeriod($yearId, 'فترة FUA');
        $this->withHeader('X-Idempotency-Key', 'fua-create')
            ->post('/admission/applications', $this->applicationPayload($periodId, $schoolB, 'سارة'))
            ->assertSessionHasNoErrors();
        $applicationId = $this->applicationId('سارة');

        $this->withHeaders(['X-School-Id' => (string) $schoolB, 'X-Idempotency-Key' => 'fua-review'])
            ->post("/admission/applications/{$applicationId}/transition", ['to_status' => 3])
            ->assertSessionHasNoErrors();

        $this->get("/admission/follow-up/applications/{$applicationId}?school_id={$schoolB}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admission/follow-up-show')
                ->where('application.id', $applicationId)
                ->where('application.school_id', $schoolB)
                ->where('application.status', 3)
                ->where('application.mother_name', 'زينب')
                ->where('application.history', fn ($history) => collect($history)
                    ->contains(fn ($event) => $event['from_status'] === 2 && $event['to_status'] === 3))
                ->where('application.allowed_transitions', fn ($targets) => collect($targets)->contains(4)
                    && ! collect($targets)->contains(9))
                ->where('authorization.can_manage', true));
    }

    #[Test]
    public function an_application_of_an_unlinked_school_is_not_found(): void
    {
        [$schoolA, $schoolB, $yearId] = $this->twoSchoolsAndYear('FUB');
        $this->actingAsManagerOf([$schoolA, $schoolB], $schoolB);
        $periodId = $this->openPeriod($yearId, 'فترة FUB');
        $this->withHeader('X-Idempotency-Key', 'fub-create')
            ->post('/admission/applications', $this->applicationPayload($periodId, $schoolB, 'ليلى'))
            ->assertSessionHasNoErrors();
        $applicationId = $this->applicationId('ليلى');

        $this->actingAsManagerOf([$schoolA], $schoolA);
        $this->get("/admission/follow-up/applications/{$applicationId}?school_id={$schoolB}")->assertNotFound();
        // Its own school does not hold it either.
        $this->get("/admission/follow-up/applications/{$applicationId}?school_id={$schoolA}")->assertNotFound();
    }

    #[Test]
    public function view_of_the_selection_returns_only_the_applications_of_the_users_schools(): void
    {
        [$schoolA, $schoolB, $yearId] = $this->twoSchoolsAndYear('FUV');
        $this->actingAsManagerOf([$schoolA, $schoolB], $schoolA);
        $periodId = $this->openPeriod($yearId, 'فترة FUV');
        foreach ([[$schoolA, 'هدى'], [$schoolB, 'مريم']] as [$schoolId, $name]) {
            $this->withHeader('X-Idempotency-Key', 'fuv-'.$name)
                ->post('/admission/applications', $this->applicationPayload($periodId, $schoolId, $name))
                ->assertSessionHasNoErrors();
        }
        $inA = $this->applicationId('هدى');
        $inB = $this->applicationId('مريم');

        $this->actingAsManagerOf([$schoolA], $schoolA);
        $response = $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) Inertia::getVersion(),
            'X-Inertia-Partial-Component' => 'admission/follow-up-list',
            'X-Inertia-Partial-Data' => 'viewing',
        ])->get("/admission/follow-up/list?view={$inA}:{$schoolA},{$inB}:{$schoolB}");

        $response->assertOk();
        $viewing = $response->json('props.viewing');
        $this->assertSame([$inA], array_column($viewing, 'id'), 'the unlinked school is left out');
        $this->assertSame('زينب', $viewing[0]['mother_name']);
        $this->assertArrayHasKey('history', $viewing[0]);
        $this->assertArrayNotHasKey('workspace', $response->json('props'), 'the roster is not read again');
    }

    /** @return array{int, int, int} */
    private function twoSchoolsAndYear(string $tag): array
    {
        $schoolA = $this->createSchool("SCH-{$tag}-A", "School {$tag} A");
        $schoolB = $this->createSchool("SCH-{$tag}-B", "School {$tag} B");
        foreach ([$schoolA, $schoolB] as $schoolId) {
            $branchId = (int) DB::table(SchemaHelper::qualified('organization', 'branches'))->insertGetId([
                'school_id' => $schoolId, 'code' => 'BR-'.$schoolId, 'name' => 'الصناعي', 'status' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->createDepartmentForSchool($schoolId, 'كهرباء', $branchId);
        }

        return [$schoolA, $schoolB, $this->createAcademicYear("AY-{$tag}")];
    }

    /** @param  list<int>  $schoolIds */
    private function actingAsManagerOf(array $schoolIds, int $contextSchoolId): User
    {
        $user = User::factory()->create();
        $seeder = app(SecurityPermissionSeeder::class);
        foreach ($schoolIds as $schoolId) {
            $seeder->grantStudentManager($user, $schoolId);
            $seeder->assignRole($user, 'admission_manager', $schoolId);
        }
        $this->actingAs($user);
        $this->withSession(['current_school_id' => $contextSchoolId]);

        return $user;
    }

    private function openPeriod(int $yearId, string $name): int
    {
        $this->withHeader('X-Idempotency-Key', 'period-'.md5($name))
            ->post('/admission/periods', [
                'academic_year_id' => $yearId,
                'directorate_id' => (int) DB::table(SchemaHelper::qualified('organization', 'directorates'))->orderBy('id')->value('id'),
                'name' => $name,
                'start_date' => '2026-09-02 08:00',
                'end_date' => null,
            ])
            ->assertSessionHasNoErrors();

        return (int) DB::table(SchemaHelper::qualified('admission', 'application_periods'))
            ->where('name', $name)->value('id');
    }

    /** @return array<string, mixed> */
    private function applicationPayload(int $periodId, int $schoolId, string $firstName): array
    {
        return [
            'application_period_id' => $periodId,
            'target_school_id' => $schoolId,
            'request_kind' => 2,
            'first_name' => $firstName,
            'father_name' => 'علي',
            'grandfather_name' => 'حسن',
            'great_grandfather_name' => 'محمد',
            'last_name' => 'الكاظمي',
            'mother_name' => 'زينب',
            'maternal_father_name' => 'كريم',
            'maternal_grandfather_name' => 'جاسم',
            'birth_date' => '2010-03-15',
            'birth_place' => 'بغداد',
            'gender' => 1,
            'intended_grade_name' => 'الأول',
            'branch_name' => 'الصناعي',
            'department_name' => 'كهرباء',
        ];
    }

    private function applicationId(string $firstName): int
    {
        DB::statement("SELECT set_config('app.current_school_id', '', false)");

        return (int) DB::table(SchemaHelper::qualified('admission', 'applications'))
            ->where('first_name', $firstName)
            ->value('id');
    }
}
