<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseAuditLoginHistoryHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function login_history_has_force_rls(): void
    {
        $row = DB::selectOne("
            SELECT c.relrowsecurity AS rls, c.relforcerowsecurity AS force_rls
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'audit' AND c.relname = 'login_history'
        ");
        $this->assertTrue((bool) $row->rls);
        $this->assertTrue((bool) $row->force_rls);
    }

    #[Test]
    public function manager_can_record_and_list_login_history(): void
    {
        $schoolId = $this->createSchool('SCH-LOGIN-1', 'Login History 1');
        $target = User::factory()->create();
        $this->actingAsAuditManagerForSchool($schoolId);

        $id = (int) $this->postJson('/api/v1/audit/login-history', [
            'user_id' => $target->id,
            'login_status' => 1,
        ], ['X-Idempotency-Key' => 'login-hist-1'])
            ->assertCreated()
            ->json('data.login_history_id');

        $this->postJson('/api/v1/audit/login-history', [
            'user_id' => $target->id,
            'login_status' => 1,
        ], ['X-Idempotency-Key' => 'login-hist-1'])
            ->assertOk()
            ->assertJsonPath('data.from_idempotency', true);

        $this->getJson('/api/v1/audit/login-history?user_id='.$target->id)
            ->assertOk()
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.login_status', 1);

        $this->assertDatabaseHas(SchemaHelper::qualified('audit', 'login_history'), [
            'id' => $id,
            'school_id' => $schoolId,
            'user_id' => $target->id,
        ]);
    }

    #[Test]
    public function viewer_cannot_record_login_history(): void
    {
        $schoolId = $this->createSchool('SCH-LOGIN-2', 'Login History 2');
        $target = User::factory()->create();
        $this->actingAsAuditViewerForSchool($schoolId);

        $this->postJson('/api/v1/audit/login-history', [
            'user_id' => $target->id,
            'login_status' => 2,
        ], ['X-Idempotency-Key' => 'login-deny'])
            ->assertForbidden();
    }
}
