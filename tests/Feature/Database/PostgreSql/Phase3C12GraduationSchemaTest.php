<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/**
 * Phase 3C.12 / 3C.12A — Graduation schema + RLS catalog checks on disposable sis_test.
 * Uses PostgreSqlIntegrationTestCase (SchemaHelper::dropSchemas before migrate:fresh).
 */
class Phase3C12GraduationSchemaTest extends PostgreSqlIntegrationTestCase
{
    /** @var list<string> */
    private array $tables = [
        'eligibility_policies',
        'eligibility_policy_versions',
        'requirement_definitions',
        'requirement_definition_versions',
        'completion_outcomes',
        'completion_outcome_versions',
        'evidence_sets',
        'evidence_items',
        'requirement_evaluations',
        'graduation_approvals',
        'graduation_awards',
        'graduation_award_versions',
        'outcome_supersessions',
        'revocation_records',
    ];

    #[Test]
    public function graduation_schema_is_registered_and_tables_exist(): void
    {
        $this->assertContains('graduation', SchemaHelper::schemas());
        $this->assertSame('sis_test', strtolower((string) DB::connection()->getDatabaseName()));

        foreach ($this->tables as $table) {
            $this->assertTrue(
                Schema::hasTable(SchemaHelper::qualified('graduation', $table)),
                "Missing graduation.{$table}"
            );
        }
    }

    #[Test]
    public function stale_blueprint_graduation_tables_are_not_created(): void
    {
        $this->assertFalse(Schema::hasTable(SchemaHelper::qualified('graduation', 'eligibility_rules')));
        $this->assertFalse(Schema::hasTable(SchemaHelper::qualified('graduation', 'records')));
    }

    #[Test]
    public function postgresql_rls_force_and_fail_closed_policies_exist(): void
    {
        foreach ($this->tables as $table) {
            $row = DB::selectOne('
                SELECT c.relrowsecurity AS rls, c.relforcerowsecurity AS force_rls
                FROM pg_class c
                JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = ? AND c.relname = ?
            ', ['graduation', $table]);

            $this->assertNotNull($row, $table);
            $this->assertTrue((bool) $row->rls, "RLS missing on {$table}");
            $this->assertTrue((bool) $row->force_rls, "FORCE RLS missing on {$table}");

            $policy = DB::selectOne('
                SELECT 1 AS ok
                FROM pg_policies
                WHERE schemaname = ? AND tablename = ? AND policyname = ?
            ', ['graduation', $table, "{$table}_school_isolation"]);

            $this->assertNotNull($policy, "Policy missing on {$table}");
        }
    }

    #[Test]
    public function postgresql_reject_delete_triggers_exist_on_official_history_tables(): void
    {
        $protected = [
            'completion_outcome_versions',
            'graduation_award_versions',
            'revocation_records',
            'outcome_supersessions',
        ];

        foreach ($protected as $table) {
            $exists = DB::selectOne('
                SELECT 1 AS ok
                FROM pg_trigger t
                JOIN pg_class c ON c.oid = t.tgrelid
                JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = ? AND c.relname = ? AND t.tgname = ? AND NOT t.tgisinternal
            ', ['graduation', $table, "{$table}_reject_delete"]);

            $this->assertNotNull($exists, "reject_delete missing on {$table}");
        }
    }

    #[Test]
    public function rls_fail_closed_without_school_context(): void
    {
        DB::statement("SELECT set_config('app.current_school_id', '', false)");

        $count = DB::table(SchemaHelper::qualified('graduation', 'completion_outcomes'))->count();
        $this->assertSame(0, $count);
    }
}
