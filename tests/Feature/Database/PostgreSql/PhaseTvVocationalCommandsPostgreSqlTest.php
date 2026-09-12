<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Vocational\Commands\CreateSpecializationCommand;
use App\Application\Vocational\Commands\CreateSpecializationHandler;
use App\Application\Vocational\Commands\CreateTrackCommand;
use App\Application\Vocational\Commands\CreateTrackHandler;
use App\Application\Vocational\Commands\DeactivateSpecializationCommand;
use App\Application\Vocational\Commands\DeactivateSpecializationHandler;
use App\Application\Vocational\Commands\DeactivateSpecializationSubjectCommand;
use App\Application\Vocational\Commands\DeactivateSpecializationSubjectHandler;
use App\Application\Vocational\Commands\LinkSpecializationSubjectCommand;
use App\Application\Vocational\Commands\LinkSpecializationSubjectHandler;
use App\Database\SchemaHelper;
use App\Domain\Vocational\ValueObjects\VocationalCatalogStatus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseTvVocationalCommandsPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function specialization_track_and_subject_link_lifecycle(): void
    {
        $schoolId = $this->createSchool('SCH-TV-V06', 'Voc U06');
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $spec = $this->app->make(CreateSpecializationHandler::class)->handle(new CreateSpecializationCommand(
            schoolId: $schoolId,
            code: 'ELEC',
            name: 'Electronics',
            idempotencyKey: 'v06-spec',
            description: 'Electronics track family',
        ));
        $this->assertTrue($spec->success);

        $track = $this->app->make(CreateTrackHandler::class)->handle(new CreateTrackCommand(
            schoolId: $schoolId,
            specializationId: (int) $spec->specializationId,
            code: 'EL1',
            name: 'Electronics 1',
            idempotencyKey: 'v06-track',
        ));
        $this->assertTrue($track->success);

        $subjectId = (int) DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->insertGetId([
            'code' => 'S'.substr(uniqid(), -8),
            'name' => 'Circuits',
            'subject_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $link = $this->app->make(LinkSpecializationSubjectHandler::class)->handle(new LinkSpecializationSubjectCommand(
            schoolId: $schoolId,
            specializationId: (int) $spec->specializationId,
            subjectId: $subjectId,
            idempotencyKey: 'v06-link',
            isRequired: true,
            creditHours: 3,
        ));
        $this->assertTrue($link->success);
        $this->assertDatabaseHas(SchemaHelper::qualified('vocational', 'specialization_subjects'), [
            'id' => $link->linkId,
            'status' => VocationalCatalogStatus::Active->value,
        ]);

        $this->app->make(DeactivateSpecializationSubjectHandler::class)->handle(new DeactivateSpecializationSubjectCommand(
            schoolId: $schoolId,
            linkId: (int) $link->linkId,
            idempotencyKey: 'v06-unlink',
        ));
        $this->assertDatabaseHas(SchemaHelper::qualified('vocational', 'specialization_subjects'), [
            'id' => $link->linkId,
            'status' => VocationalCatalogStatus::Inactive->value,
        ]);

        $this->app->make(DeactivateSpecializationHandler::class)->handle(new DeactivateSpecializationCommand(
            schoolId: $schoolId,
            specializationId: (int) $spec->specializationId,
            idempotencyKey: 'v06-spec-off',
        ));
        $this->assertDatabaseHas(SchemaHelper::qualified('vocational', 'specializations'), [
            'id' => $spec->specializationId,
            'status' => VocationalCatalogStatus::Inactive->value,
        ]);
    }
}
