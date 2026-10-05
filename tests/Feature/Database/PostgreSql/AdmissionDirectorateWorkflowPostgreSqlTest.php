<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/**
 * المديرية → المدارس → الفروع → الاختصاصات on the admission page:
 * - a period belongs to an academic year and a directorate (only the user's directorates);
 * - only the directorate's schools file applications in its period (legacy NULL = every school);
 * - the application's branch belongs to its school and the department to that branch;
 * - «متابعة طلبات التقديم» lists the students of each of the user's schools, and edits
 *   are saved inside the chosen school.
 *
 * Generated catalog: كربلاء (الحسين: الصناعي→كهرباء/ميكانيك، التجاري→محاسبة؛ الزهراء: الزراعي→إنتاج نباتي؛
 * الفرات: no branches) and النجف (الكوفة: الصناعي→تبريد).
 */
final class AdmissionDirectorateWorkflowPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    private int $karbala;

    private int $najaf;

    private int $husain;

    private int $zahraa;

    private int $furat;

    private int $kufa;

    private int $yearId;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $ministry = (int) DB::table(SchemaHelper::qualified('organization', 'ministries'))->insertGetId([
            'code' => 'MIN-DIR', 'name' => 'وزارة التربية', 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->karbala = $this->directorate($ministry, 'DIR-KRB', 'تربية كربلاء');
        $this->najaf = $this->directorate($ministry, 'DIR-NJF', 'تربية النجف');

        $this->husain = $this->school('SCH-HSN', 'اعدادية الحسين المهنية', $this->karbala);
        $this->zahraa = $this->school('SCH-ZHR', 'اعدادية الزهراء المهنية', $this->karbala);
        $this->furat = $this->school('SCH-FRT', 'اعدادية الفرات المهنية', $this->karbala);
        $this->kufa = $this->school('SCH-KFA', 'اعدادية الكوفة المهنية', $this->najaf);

        $industrial = $this->branch($this->husain, 'الصناعي');
        $this->createDepartmentForSchool($this->husain, 'كهرباء', $industrial);
        $this->createDepartmentForSchool($this->husain, 'ميكانيك', $industrial);
        $commercial = $this->branch($this->husain, 'التجاري');
        $this->createDepartmentForSchool($this->husain, 'محاسبة', $commercial);
        $agricultural = $this->branch($this->zahraa, 'الزراعي');
        $this->createDepartmentForSchool($this->zahraa, 'إنتاج نباتي', $agricultural);
        $kufaIndustrial = $this->branch($this->kufa, 'الصناعي');
        $this->createDepartmentForSchool($this->kufa, 'تبريد', $kufaIndustrial);

        $this->yearId = $this->createAcademicYear('AY-DIR');
        $this->manager = $this->managerOf([$this->husain, $this->zahraa, $this->furat, $this->kufa], $this->husain);
    }

    #[Test]
    public function period_requires_one_of_the_users_directorates(): void
    {
        $this->post('/admission/periods', $this->periodPayload('بلا مديرية', null))
            ->assertSessionHasErrors(['directorate_id']);

        // A Karbala-only user cannot open a period for Najaf.
        $this->managerOf([$this->husain], $this->husain);
        $this->post('/admission/periods', $this->periodPayload('فترة النجف', $this->najaf))
            ->assertSessionHasErrors(['directorate_id']);

        $this->post('/admission/periods', $this->periodPayload('فترة كربلاء', $this->karbala))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas(SchemaHelper::qualified('admission', 'application_periods'), [
            'name' => 'فترة كربلاء', 'directorate_id' => $this->karbala, 'academic_year_id' => $this->yearId,
        ]);
    }

    #[Test]
    public function admission_page_lists_directorates_with_their_schools_and_periods_of_the_users_directorates(): void
    {
        $karbalaPeriod = $this->openPeriod('فترة كربلاء', $this->karbala);
        $najafPeriod = $this->openPeriod('فترة النجف', $this->najaf);

        $this->get("/admission?academic_year_id={$this->yearId}")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->where('workspace.school_options', fn ($schools) => collect($schools)
                    ->mapWithKeys(fn ($school) => [$school['name'] => $school['directorate_id']])
                    ->all() === [
                        'اعدادية الحسين المهنية' => $this->karbala,
                        'اعدادية الزهراء المهنية' => $this->karbala,
                        'اعدادية الفرات المهنية' => $this->karbala,
                        'اعدادية الكوفة المهنية' => $this->najaf,
                    ])
                ->where('workspace.periods', fn ($periods) => collect($periods)->pluck('id')->sort()->values()->all()
                    === collect([$karbalaPeriod, $najafPeriod])->sort()->values()->all()));

        // A Karbala-only user does not see the Najaf period.
        $this->managerOf([$this->husain], $this->husain);
        $this->get("/admission?academic_year_id={$this->yearId}")
            ->assertInertia(fn ($page) => $page
                ->where('workspace.periods', fn ($periods) => collect($periods)->pluck('id')->all() === [$karbalaPeriod])
                ->where('workspace.periods.0.directorate_name', 'تربية كربلاء'));
    }

    #[Test]
    public function application_school_branch_and_department_follow_the_catalog_and_the_period_directorate(): void
    {
        $period = $this->openPeriod('فترة كربلاء', $this->karbala);

        // Valid: الحسين / الصناعي / كهرباء.
        $this->postApplication($period, $this->husain, 'الصناعي', 'كهرباء', 'علي')->assertSessionHasNoErrors();
        // Valid: another Karbala school with its own branch.
        $this->postApplication($period, $this->zahraa, 'الزراعي', 'إنتاج نباتي', 'زينب')->assertSessionHasNoErrors();

        // Branch of another school.
        $this->postApplication($period, $this->husain, 'الزراعي', 'إنتاج نباتي', 'خطأ1')->assertSessionHasErrors(['branch_name']);
        // Department of another branch of the same school.
        $this->postApplication($period, $this->husain, 'الصناعي', 'محاسبة', 'خطأ2')->assertSessionHasErrors(['department_name']);
        // School without branches.
        $this->postApplication($period, $this->furat, 'الصناعي', 'كهرباء', 'خطأ3')->assertSessionHasErrors(['branch_name']);
        // Najaf school in a Karbala period.
        $this->from('/admission')
            ->postApplication($period, $this->kufa, 'الصناعي', 'تبريد', 'خطأ4')
            ->assertSessionHas('error', 'admission.period_directorate_mismatch');

        $names = [];
        foreach ([$this->husain, $this->zahraa, $this->furat, $this->kufa] as $schoolId) {
            DB::statement("SELECT set_config('app.current_school_id', ?, false)", [(string) $schoolId]);
            $names = [...$names, ...DB::table(SchemaHelper::qualified('admission', 'applications'))->where('school_id', $schoolId)->pluck('first_name')->all()];
        }
        sort($names);
        $this->assertSame(['زينب', 'علي'], $names, 'only the valid applications were filed');

        // A legacy period without a directorate stays open to every school.
        $legacy = $this->openPeriod('فترة قديمة', $this->karbala);
        DB::statement("SELECT set_config('app.current_school_id', ?, false)", [(string) $this->husain]);
        DB::table(SchemaHelper::qualified('admission', 'application_periods'))->where('id', $legacy)->update(['directorate_id' => null]);
        $this->postApplication($legacy, $this->kufa, 'الصناعي', 'تبريد', 'كوفي')->assertSessionHasNoErrors();
    }

    #[Test]
    public function transfer_to_a_school_of_another_directorate_keeps_the_period_rule(): void
    {
        $period = $this->openPeriod('فترة كربلاء', $this->karbala);
        $this->postApplication($period, $this->husain, 'الصناعي', 'كهرباء', 'محمد')->assertSessionHasNoErrors();
        $applicationId = $this->applicationId('محمد');

        $this->from('/transfers')->post("/transfers/applications/{$applicationId}", [
            'target_school_id' => $this->kufa, 'request_kind' => 2, 'application_period_id' => $period,
        ])->assertSessionHasErrors(['transfer' => 'admission.period_directorate_mismatch']);

        $this->from('/transfers')->post("/transfers/applications/{$applicationId}", [
            'target_school_id' => $this->zahraa, 'request_kind' => 2, 'application_period_id' => $period,
        ])->assertSessionHasNoErrors();
    }

    #[Test]
    public function follow_up_roster_lists_each_schools_students_and_saves_in_the_chosen_school(): void
    {
        $period = $this->openPeriod('فترة كربلاء', $this->karbala);
        $this->postApplication($period, $this->husain, 'الصناعي', 'ميكانيك', 'حسن')->assertSessionHasNoErrors();
        $this->postApplication($period, $this->zahraa, 'الزراعي', 'إنتاج نباتي', 'مريم')->assertSessionHasNoErrors();
        // Submitted applications appear in the roster.
        DB::statement("SELECT set_config('app.current_school_id', '', false)");
        foreach ([$this->husain, $this->zahraa] as $schoolId) {
            DB::statement("SELECT set_config('app.current_school_id', ?, false)", [(string) $schoolId]);
            DB::table(SchemaHelper::qualified('admission', 'applications'))->where('school_id', $schoolId)->update(['status' => 2]);
        }

        $this->get("/admission?academic_year_id={$this->yearId}&include_accepted_roster=1")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->where('workspace.accepted_students', fn ($rows) => collect($rows)
                    ->mapWithKeys(fn ($row) => [$row['full_name'] => $row['school_name']])
                    ->sortKeys()
                    ->all() === [
                        'حسن علي حسن محمد الكاظمي' => 'اعدادية الحسين المهنية',
                        'مريم علي حسن محمد الكاظمي' => 'اعدادية الزهراء المهنية',
                    ]));

        // Context is الحسين; the edit of a الزهراء student is saved inside الزهراء.
        $maryam = $this->applicationId('مريم');
        $this->withHeader('X-School-Id', (string) $this->zahraa)
            ->put('/admission/applications/follow-up', ['updates' => [[
                'application_id' => $maryam, 'full_name' => 'مريم علي حسن محمد الحلي',
            ]]])
            ->assertSessionHasNoErrors();

        DB::statement("SELECT set_config('app.current_school_id', ?, false)", [(string) $this->zahraa]);
        $this->assertSame('الحلي', DB::table(SchemaHelper::qualified('admission', 'applications'))->where('id', $maryam)->value('last_name'));

        // A school the user is not linked to cannot be targeted.
        $this->managerOf([$this->husain], $this->husain);
        $this->withHeader('X-School-Id', (string) $this->zahraa)
            ->put('/admission/applications/follow-up', ['updates' => [[
                'application_id' => $maryam, 'full_name' => 'مريم مخترقة',
            ]]]);
        DB::statement("SELECT set_config('app.current_school_id', ?, false)", [(string) $this->zahraa]);
        $this->assertSame('الحلي', DB::table(SchemaHelper::qualified('admission', 'applications'))->where('id', $maryam)->value('last_name'));
    }

    private function directorate(int $ministry, string $code, string $name): int
    {
        return (int) DB::table(SchemaHelper::qualified('organization', 'directorates'))->insertGetId([
            'ministry_id' => $ministry, 'code' => $code, 'name' => $name, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function school(string $code, string $name, int $directorateId): int
    {
        return (int) DB::table(SchemaHelper::qualified('organization', 'schools'))->insertGetId([
            'code' => $code, 'directorate_id' => $directorateId, 'name' => $name, 'school_type' => 2, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function branch(int $schoolId, string $name): int
    {
        return (int) DB::table(SchemaHelper::qualified('organization', 'branches'))->insertGetId([
            'school_id' => $schoolId, 'code' => 'BR-'.$schoolId.'-'.substr(md5($name), 0, 6), 'name' => $name, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @param  list<int>  $schoolIds */
    private function managerOf(array $schoolIds, int $contextSchoolId): User
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

    /** @return array<string, mixed> */
    private function periodPayload(string $name, ?int $directorateId): array
    {
        return array_filter([
            'academic_year_id' => $this->yearId,
            'directorate_id' => $directorateId,
            'name' => $name,
            'start_date' => '2026-09-02 08:00',
        ], static fn ($value): bool => $value !== null);
    }

    private function openPeriod(string $name, int $directorateId): int
    {
        $this->post('/admission/periods', $this->periodPayload($name, $directorateId))->assertSessionHasNoErrors();

        return (int) DB::table(SchemaHelper::qualified('admission', 'application_periods'))->where('name', $name)->value('id');
    }

    private function postApplication(int $periodId, int $schoolId, string $branch, string $department, string $firstName): TestResponse
    {
        return $this->withHeader('X-Idempotency-Key', 'dir-'.md5($firstName.$schoolId.$branch.$department))
            ->post('/admission/applications', [
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
                'birth_place' => 'كربلاء',
                'gender' => 1,
                'intended_grade_name' => 'الأول',
                'branch_name' => $branch,
                'department_name' => $department,
            ]);
    }

    private function applicationId(string $firstName): int
    {
        DB::statement("SELECT set_config('app.current_school_id', '', false)");
        foreach ([$this->husain, $this->zahraa, $this->furat, $this->kufa] as $schoolId) {
            DB::statement("SELECT set_config('app.current_school_id', ?, false)", [(string) $schoolId]);
            $id = DB::table(SchemaHelper::qualified('admission', 'applications'))->where('school_id', $schoolId)->where('first_name', $firstName)->value('id');
            if ($id !== null) {
                return (int) $id;
            }
        }
        $this->fail("application {$firstName} not found");
    }
}
