<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Admission\Services\CreateApplicationDraftGuard;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/**
 * Opening an application period from the admission page: the academic-year `exists`
 * rule must resolve the schema-qualified table (not read "academic" as a connection),
 * and the chosen school must be the request's school context.
 */
final class AdmissionOpenPeriodHttpPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function opens_period_for_the_selected_school(): void
    {
        $schoolId = $this->createSchool('SCH-OPN', 'Open Period School');
        $yearId = $this->createAcademicYear('AY-OPN');
        $this->actingAsAdmissionManager($schoolId);

        $this->withHeader('X-School-Id', (string) $schoolId)
            ->post('/admission/periods', [
                'academic_year_id' => $yearId,
                'directorate_id' => $this->directorateOfSchool($schoolId),
                'name' => 'فترة التقديم الأولى',
                'start_date' => '2026-09-05 08:00',
                'end_date' => '2026-10-05 14:00',
                'max_applications' => null,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas(SchemaHelper::qualified('admission', 'application_periods'), [
            'academic_year_id' => $yearId,
            'name' => 'فترة التقديم الأولى',
        ]);
    }

    #[Test]
    public function opens_an_open_ended_period_without_end_date_and_accepts_drafts_in_it(): void
    {
        $schoolId = $this->createSchool('SCH-OPE', 'Open Ended School');
        $yearId = $this->createAcademicYear('AY-OPE');
        $this->actingAsAdmissionManager($schoolId);

        $this->post('/admission/periods', [
            'academic_year_id' => $yearId,
            'directorate_id' => $this->directorateOfSchool($schoolId),
            'name' => 'فترة مفتوحة',
            'start_date' => '2026-09-05 08:00',
            'end_date' => null,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $periodId = (int) DB::table(SchemaHelper::qualified('admission', 'application_periods'))
            ->where('name', 'فترة مفتوحة')->whereNull('end_date')->value('id');
        $this->assertGreaterThan(0, $periodId);

        $this->travelTo('2026-12-01 10:00');
        $period = app(CreateApplicationDraftGuard::class)->assertOpenPeriod($periodId, $schoolId, 1);
        $this->assertNull($period['end_date']);
    }

    #[Test]
    public function rejects_missing_dates_and_unknown_year_with_validation_errors(): void
    {
        $schoolId = $this->createSchool('SCH-OPV', 'Validation School');
        $this->actingAsAdmissionManager($schoolId);

        $this->from('/admission')
            ->post('/admission/periods', [
                'academic_year_id' => 999999,
                'name' => 'فترة',
                'start_date' => null,
                'end_date' => null,
            ])
            ->assertRedirect('/admission')
            ->assertSessionHasErrors(['academic_year_id', 'start_date'])
            ->assertSessionDoesntHaveErrors(['end_date']);
    }

    #[Test]
    public function periods_are_shared_by_every_school_and_take_no_school(): void
    {
        $schoolId = $this->createSchool('SCH-OPS', 'Context School');
        $otherSchoolId = $this->createSchool('SCH-OPO', 'Other School');
        $yearId = $this->createAcademicYear('AY-OPS');
        $this->actingAsAdmissionManager($schoolId);

        $this->from('/admission')
            ->post('/admission/periods', [
                'school_id' => $otherSchoolId,
                'academic_year_id' => $yearId,
                'name' => 'فترة',
                'start_date' => '2026-09-05 08:00',
                'end_date' => '2026-10-05 14:00',
            ])
            ->assertSessionHasErrors(['school_id']);

        $this->post('/admission/periods', [
            'academic_year_id' => $yearId,
            'directorate_id' => $this->directorateOfSchool($schoolId),
            'name' => 'فترة مشتركة',
            'start_date' => '2026-09-05 08:00',
            'end_date' => null,
        ])->assertSessionHasNoErrors();

        $period = DB::table(SchemaHelper::qualified('admission', 'application_periods'))
            ->where('name', 'فترة مشتركة')->first(['id', 'school_id']);
        $this->assertNotNull($period);
        $this->assertNull($period->school_id);

        // The other school's admission page sees the same period.
        $this->travelTo('2026-12-01 10:00');
        $this->assertSame(
            $yearId,
            app(CreateApplicationDraftGuard::class)->assertOpenPeriod((int) $period->id, $otherSchoolId, 1)['academic_year_id'],
        );
    }

    private function actingAsAdmissionManager(int $schoolId): void
    {
        $user = User::factory()->create();
        $seeder = app(SecurityPermissionSeeder::class);
        $seeder->grantStudentManager($user, $schoolId);
        $seeder->assignRole($user, 'admission_manager', $schoolId);
        $this->actingAs($user);
        $this->withSession(['current_school_id' => $schoolId]);
    }
}
