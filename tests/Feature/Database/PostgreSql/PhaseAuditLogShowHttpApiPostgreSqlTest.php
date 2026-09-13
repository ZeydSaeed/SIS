<?php

namespace Tests\Feature\Database\PostgreSql;

use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseAuditLogShowHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_show_audit_log(): void
    {
        $schoolId = $this->createSchool('SCH-AUDIT-U03', 'Audit Show U03');
        $this->actingAsAuditManagerForSchool($schoolId);

        $auditLogId = (int) $this->postJson('/api/v1/audit/logs', [
            'action' => 'enrollment.updated',
            'entity_type' => 'enrollment',
            'entity_id' => 55,
            'old_values' => ['section_id' => 1],
            'new_values' => ['section_id' => 2],
        ], ['X-Idempotency-Key' => 'audit-show-u03'])->json('data.audit_log_id');

        $this->getJson('/api/v1/audit/logs/'.$auditLogId)
            ->assertOk()
            ->assertJsonPath('data.id', $auditLogId)
            ->assertJsonPath('data.action', 'enrollment.updated')
            ->assertJsonPath('data.entity_type', 'enrollment')
            ->assertJsonPath('data.entity_id', 55)
            ->assertJsonPath('data.old_values.section_id', 1)
            ->assertJsonPath('data.new_values.section_id', 2);
    }
}
