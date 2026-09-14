<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseAuditLoginHistoryShowHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_show_login_history_entry(): void
    {
        $schoolId = $this->createSchool('SCH-AUD-U04', 'Audit Show Login');
        $target = User::factory()->create();
        $this->actingAsAuditManagerForSchool($schoolId);

        $entryId = (int) $this->postJson('/api/v1/audit/login-history', [
            'user_id' => $target->id,
            'login_status' => 1,
        ], ['X-Idempotency-Key' => 'audit-u04-login'])->json('data.login_history_id');

        $this->getJson('/api/v1/audit/login-history/'.$entryId)
            ->assertOk()
            ->assertJsonPath('data.id', $entryId)
            ->assertJsonPath('data.user_id', $target->id)
            ->assertJsonPath('data.login_status', 1)
            ->assertJsonPath('data.school_id', $schoolId);
    }
}
