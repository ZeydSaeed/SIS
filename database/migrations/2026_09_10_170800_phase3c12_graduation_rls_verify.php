<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3C.12 M17′ — VERIFY-ONLY: every tenant graduation table has ENABLE+FORCE RLS + fail-closed policy.
 */
return new class extends Migration
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

    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        foreach ($this->tables as $table) {
            $row = DB::selectOne('
                SELECT c.relrowsecurity AS rls, c.relforcerowsecurity AS force_rls
                FROM pg_class c
                JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = ? AND c.relname = ?
            ', ['graduation', $table]);

            if ($row === null) {
                throw new \RuntimeException("Graduation table missing during RLS verify: {$table}");
            }

            if (! $row->rls || ! $row->force_rls) {
                throw new \RuntimeException("RLS/FORCE missing on graduation.{$table}");
            }

            $policy = DB::selectOne('
                SELECT 1 AS ok
                FROM pg_policies
                WHERE schemaname = ? AND tablename = ? AND policyname = ?
            ', ['graduation', $table, "{$table}_school_isolation"]);

            if ($policy === null) {
                throw new \RuntimeException("Fail-closed policy missing on graduation.{$table}");
            }
        }
    }

    public function down(): void
    {
        // Verify-only — nothing to reverse.
    }
};
