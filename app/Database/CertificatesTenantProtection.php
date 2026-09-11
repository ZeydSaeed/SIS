<?php

namespace App\Database;

use Illuminate\Support\Facades\DB;

/**
 * Phase 4.1 — Option A tenant protection for certificates.* tables.
 * ENABLE + FORCE RLS + fail-closed school isolation (same pattern as Graduation).
 */
final class CertificatesTenantProtection
{
    public static function protect(string $table): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        $qualified = "certificates.{$table}";
        $policy = "{$table}_school_isolation";

        DB::statement("ALTER TABLE {$qualified} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$qualified} FORCE ROW LEVEL SECURITY");
        DB::statement("
            CREATE POLICY {$policy} ON {$qualified}
            USING (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
            )
            WITH CHECK (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
            )
        ");
    }

    public static function unprotect(string $table): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        $qualified = "certificates.{$table}";
        $policy = "{$table}_school_isolation";

        DB::statement("DROP POLICY IF EXISTS {$policy} ON {$qualified}");
        DB::statement("ALTER TABLE {$qualified} NO FORCE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$qualified} DISABLE ROW LEVEL SECURITY");
    }
}
