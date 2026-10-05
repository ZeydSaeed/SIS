<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/**
 * Admission periods belong to the academic year; each application belongs to the school
 * chosen on the form. School / request kind / academic year change only on النقل.
 */
final class AdmissionSchoolChoiceAndTransferPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function application_is_filed_in_the_school_chosen_on_the_form(): void
    {
        [$schoolA, $schoolB, $yearId] = $this->twoSchoolsAndYear('SCF');
        $this->actingAsManagerOf([$schoolA, $schoolB], $schoolA);
        $periodId = $this->openPeriod($yearId, 'فترة SCF');

        $this->withHeader('X-Idempotency-Key', 'scf-create-1')
            ->post('/admission/applications', $this->applicationPayload($periodId, $schoolB, 2, 'أحمد'))
            ->assertSessionHasNoErrors();

        $row = $this->applicationByName('أحمد');
        $this->assertSame($schoolB, (int) $row->school_id);
        $this->assertSame($schoolB, (int) $row->target_school_id);
        $this->assertSame(2, (int) $row->request_kind);
        $this->assertSame(2, (int) $row->status, 'new applications are submitted straight away');
    }

    #[Test]
    public function unlinked_or_inactive_schools_and_missing_request_kind_are_rejected(): void
    {
        [$schoolA, $schoolB, $yearId] = $this->twoSchoolsAndYear('SCR');
        $this->actingAsManagerOf([$schoolA], $schoolA);
        $periodId = $this->openPeriod($yearId, 'فترة SCR');

        $this->withHeader('X-Idempotency-Key', 'scr-1')
            ->post('/admission/applications', $this->applicationPayload($periodId, $schoolB, 2, 'غريب'))
            ->assertSessionHasErrors(['target_school_id']);

        $payload = $this->applicationPayload($periodId, $schoolA, 2, 'بلا نوع');
        unset($payload['request_kind']);
        $this->withHeader('X-Idempotency-Key', 'scr-2')
            ->post('/admission/applications', $payload)
            ->assertSessionHasErrors(['request_kind']);

        DB::table(SchemaHelper::qualified('organization', 'schools'))->where('id', $schoolA)->update(['status' => 2]);
        $this->withHeader('X-Idempotency-Key', 'scr-3')
            ->post('/admission/applications', $this->applicationPayload($periodId, $schoolA, 2, 'معطلة'))
            ->assertSessionHasErrors(['target_school_id']);
    }

    #[Test]
    public function editing_an_application_cannot_change_school_kind_or_year(): void
    {
        [$schoolA, , $yearId] = $this->twoSchoolsAndYear('SCE');
        $this->actingAsManagerOf([$schoolA], $schoolA);
        $periodId = $this->openPeriod($yearId, 'فترة SCE');
        $this->withHeader('X-Idempotency-Key', 'sce-create')
            ->post('/admission/applications', $this->applicationPayload($periodId, $schoolA, 2, 'مقفل'))
            ->assertSessionHasNoErrors();
        $id = (int) $this->applicationByName('مقفل')->id;

        foreach (['target_school_id' => $schoolA, 'request_kind' => 1, 'application_period_id' => $periodId] as $field => $value) {
            $this->put("/admission/applications/{$id}", ['notes' => 'x', $field => $value])
                ->assertSessionHasErrors([$field]);
        }
    }

    #[Test]
    public function transfer_moves_school_kind_and_year_and_keeps_history(): void
    {
        [$schoolA, $schoolB, $yearId] = $this->twoSchoolsAndYear('TRF');
        $nextYearId = $this->createAcademicYear('AY-TRF-NEXT');
        $user = $this->actingAsManagerOf([$schoolA, $schoolB], $schoolA);
        $periodId = $this->openPeriod($yearId, 'فترة TRF');
        $nextPeriodId = $this->openPeriod($nextYearId, 'فترة TRF القادمة');

        $this->withHeader('X-Idempotency-Key', 'trf-create')
            ->post('/admission/applications', $this->applicationPayload($periodId, $schoolA, 2, 'منقول'))
            ->assertSessionHasNoErrors();
        $id = (int) $this->applicationByName('منقول')->id;

        $this->get('/transfers')->assertOk()->assertInertia(fn ($page) => $page
            ->component('transfers/index')
            ->where('transfers.applications.0.id', $id));

        $this->withHeader('X-Idempotency-Key', 'trf-move')
            ->post("/transfers/applications/{$id}", [
                'target_school_id' => $schoolB,
                'request_kind' => 1,
                'application_period_id' => $nextPeriodId,
                'reason' => 'رغبة ولي الأمر',
            ])
            ->assertSessionHasNoErrors();

        $row = $this->applicationByName('منقول');
        $this->assertSame($schoolB, (int) $row->school_id);
        $this->assertSame(1, (int) $row->request_kind);
        $this->assertSame($nextPeriodId, (int) $row->application_period_id);

        $this->assertDatabaseHas(SchemaHelper::qualified('admission', 'application_transfers'), [
            'application_id' => $id,
            'from_school_id' => $schoolA,
            'to_school_id' => $schoolB,
            'from_request_kind' => 2,
            'to_request_kind' => 1,
            'from_period_id' => $periodId,
            'to_period_id' => $nextPeriodId,
            'transferred_by' => $user->id,
        ]);

        // The old school no longer lists it; the history still shows the move.
        $this->get('/transfers')->assertOk()->assertInertia(fn ($page) => $page
            ->where('transfers.applications', [])
            ->where('transfers.history.0.to_school_name', 'School TRF B'));
    }

    #[Test]
    public function transfer_rejects_unlinked_target_unchanged_and_converted_applications(): void
    {
        [$schoolA, $schoolB, $yearId] = $this->twoSchoolsAndYear('TRX');
        $this->actingAsManagerOf([$schoolA], $schoolA);
        $periodId = $this->openPeriod($yearId, 'فترة TRX');
        $this->withHeader('X-Idempotency-Key', 'trx-create')
            ->post('/admission/applications', $this->applicationPayload($periodId, $schoolA, 2, 'ثابت'))
            ->assertSessionHasNoErrors();
        $id = (int) $this->applicationByName('ثابت')->id;

        $this->withHeader('X-Idempotency-Key', 'trx-1')
            ->post("/transfers/applications/{$id}", [
                'target_school_id' => $schoolB, 'request_kind' => 2, 'application_period_id' => $periodId,
            ])
            ->assertSessionHasErrors(['target_school_id']);

        $this->withHeader('X-Idempotency-Key', 'trx-2')
            ->post("/transfers/applications/{$id}", [
                'target_school_id' => $schoolA, 'request_kind' => 2, 'application_period_id' => $periodId,
            ])
            ->assertSessionHasErrors(['transfer' => 'admission.transfer_nothing_changed']);

        DB::table(SchemaHelper::qualified('admission', 'applications'))->where('id', $id)->update(['status' => 9]);
        $this->withHeader('X-Idempotency-Key', 'trx-3')
            ->post("/transfers/applications/{$id}", [
                'target_school_id' => $schoolA, 'request_kind' => 1, 'application_period_id' => $periodId,
            ])
            ->assertSessionHasErrors(['transfer' => 'admission.transfer_application_converted']);
    }

    /** @return array{0:int,1:int,2:int} */
    private function twoSchoolsAndYear(string $tag): array
    {
        $schoolA = $this->createSchool("SCH-{$tag}-A", "School {$tag} A");
        $schoolB = $this->createSchool("SCH-{$tag}-B", "School {$tag} B");
        // Each school has its own branch الصناعي → اختصاص كهرباء (the application form's lists).
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
                // Test schools share the first directorate.
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
    private function applicationPayload(int $periodId, int $schoolId, int $requestKind, string $firstName): array
    {
        return [
            'application_period_id' => $periodId,
            'target_school_id' => $schoolId,
            'request_kind' => $requestKind,
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

    private function applicationByName(string $firstName): object
    {
        DB::statement("SELECT set_config('app.current_school_id', '', false)");
        $row = DB::table(SchemaHelper::qualified('admission', 'applications'))
            ->where('first_name', $firstName)
            ->first(['id', 'school_id', 'target_school_id', 'request_kind', 'status', 'application_period_id']);
        $this->assertNotNull($row, "application {$firstName} exists");

        return $row;
    }
}
