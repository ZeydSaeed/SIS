<?php

namespace Tests\Support\Database;

use Illuminate\Support\Facades\DB;

/**
 * Non-superuser role for meaningful RLS assertions (superusers bypass RLS).
 */
final class PostgreSqlRlsActor
{
    public const ROLE = 'sis_rls_tester';

    public static function ensureRoleAndGrants(): void
    {
        DB::statement("
            DO \$\$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = '".self::ROLE."') THEN
                    CREATE ROLE ".self::ROLE.' NOLOGIN NOSUPERUSER NOBYPASSRLS;
                END IF;
            END
            $$;
        ');

        $schemas = [
            'organization', 'academic', 'vocational', 'students', 'guardians', 'admission',
            'enrollment', 'teachers', 'curriculum', 'timetable', 'attendance', 'exams',
            'results', 'security', 'audit', 'public',
        ];

        foreach ($schemas as $schema) {
            DB::statement("GRANT USAGE ON SCHEMA {$schema} TO ".self::ROLE);
            DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA {$schema} TO ".self::ROLE);
            DB::statement("GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA {$schema} TO ".self::ROLE);
        }
    }

    public static function become(): void
    {
        self::ensureRoleAndGrants();
        DB::statement('SET LOCAL ROLE '.self::ROLE);
    }

    public static function reset(): void
    {
        DB::statement('RESET ROLE');
    }
}
