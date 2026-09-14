<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Portal\ValueObjects\PortalScopeType;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhasePortalScopeShowHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_show_portal_scope_row(): void
    {
        $schoolId = $this->createSchool('SCH-PORT-SHOW', 'Portal Scope Show');
        $yearId = $this->createAcademicYear('AY-PORT-SHOW');
        $enrollment = $this->createActiveEnrollmentForSchool($schoolId, $yearId);
        $target = User::factory()->create();
        $this->actingAsPortalScopesManagerForSchool($schoolId);

        $scopeRowId = (int) $this->postJson('/api/v1/portal/scopes', [
            'user_id' => $target->id,
            'scope_type' => PortalScopeType::STUDENT,
            'scope_id' => $enrollment->student_id,
        ], ['X-Idempotency-Key' => 'port-show-link'])->json('data.scope_row_id');

        $this->getJson('/api/v1/portal/scopes/'.$scopeRowId)
            ->assertOk()
            ->assertJsonPath('data.id', $scopeRowId)
            ->assertJsonPath('data.user_id', $target->id)
            ->assertJsonPath('data.scope_type', PortalScopeType::STUDENT)
            ->assertJsonPath('data.scope_id', (int) $enrollment->student_id);

        $this->assertDatabaseHas(SchemaHelper::qualified('security', 'scopes'), [
            'id' => $scopeRowId,
            'user_id' => $target->id,
        ]);
    }
}
