<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Security\Authorization\Permission;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseAuditLogsHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function audit_logs_have_force_rls_and_school_id(): void
    {
        $cols = collect(DB::select("
            SELECT column_name FROM information_schema.columns
            WHERE table_schema = 'audit' AND table_name = 'audit_logs'
        "))->pluck('column_name')->all();

        $this->assertContains('school_id', $cols);

        $row = DB::selectOne("
            SELECT c.relrowsecurity AS rls, c.relforcerowsecurity AS force_rls
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'audit' AND c.relname = 'audit_logs'
        ");
        $this->assertTrue((bool) $row->rls);
        $this->assertTrue((bool) $row->force_rls);
    }

    #[Test]
    public function audit_permissions_are_registered(): void
    {
        $this->assertArrayHasKey(Permission::AUDIT_VIEW, config('security.permissions'));
        $this->assertArrayHasKey(Permission::AUDIT_MANAGE, config('security.permissions'));
    }

    #[Test]
    public function manager_can_register_and_list_audit_log(): void
    {
        $schoolId = $this->createSchool('SCH-AUDIT-1', 'Audit School 1');
        $this->actingAsAuditManagerForSchool($schoolId);

        $create = $this->postJson('/api/v1/audit/logs', [
            'action' => 'student.updated',
            'entity_type' => 'student',
            'entity_id' => 42,
            'old_values' => ['status' => 1],
            'new_values' => ['status' => 2],
        ], ['X-Idempotency-Key' => 'audit-reg-1'])
            ->assertCreated();

        $auditLogId = (int) $create->json('data.audit_log_id');

        $this->postJson('/api/v1/audit/logs', [
            'action' => 'student.updated',
            'entity_type' => 'student',
            'entity_id' => 42,
            'old_values' => ['status' => 1],
            'new_values' => ['status' => 2],
        ], ['X-Idempotency-Key' => 'audit-reg-1'])
            ->assertOk()
            ->assertJsonPath('data.audit_log_id', $auditLogId)
            ->assertJsonPath('data.from_idempotency', true);

        $this->getJson('/api/v1/audit/logs?entity_type=student&entity_id=42')
            ->assertOk()
            ->assertJsonPath('data.0.id', $auditLogId)
            ->assertJsonPath('data.0.action', 'student.updated');

        $this->assertDatabaseHas(SchemaHelper::qualified('audit', 'audit_logs'), [
            'id' => $auditLogId,
            'school_id' => $schoolId,
            'entity_type' => 'student',
            'entity_id' => 42,
        ]);
    }

    #[Test]
    public function viewer_cannot_register_audit_log(): void
    {
        $schoolId = $this->createSchool('SCH-AUDIT-2', 'Audit School 2');
        $this->actingAsAuditViewerForSchool($schoolId);

        $this->postJson('/api/v1/audit/logs', [
            'action' => 'noop',
            'entity_type' => 'student',
            'entity_id' => 1,
        ], ['X-Idempotency-Key' => 'audit-deny'])
            ->assertForbidden();
    }

    #[Test]
    public function hard_delete_of_audit_logs_is_rejected(): void
    {
        $schoolId = $this->createSchool('SCH-AUDIT-3', 'Audit School 3');
        $this->actingAsAuditManagerForSchool($schoolId);

        $id = (int) $this->postJson('/api/v1/audit/logs', [
            'action' => 'keep',
            'entity_type' => 'enrollment',
            'entity_id' => 9,
        ], ['X-Idempotency-Key' => 'audit-del-1'])
            ->json('data.audit_log_id');

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table(SchemaHelper::qualified('audit', 'audit_logs'))->where('id', $id)->delete();
    }
}
