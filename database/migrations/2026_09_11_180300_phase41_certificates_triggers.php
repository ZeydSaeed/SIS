<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 4.1 — Reject-delete + immutable template-version content protection.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement("
            CREATE OR REPLACE FUNCTION certificates.reject_hard_delete()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'Hard delete of %.% is forbidden', TG_TABLE_SCHEMA, TG_TABLE_NAME;
            END;
            $$
        ");

        $rejectDeleteTables = [
            'certificate_templates',
            'certificate_template_versions',
            'certificates',
            'certificate_issuances',
            'certificate_artifacts',
            'certificate_generation_jobs',
        ];

        foreach ($rejectDeleteTables as $table) {
            DB::statement("
                CREATE TRIGGER {$table}_reject_delete
                BEFORE DELETE ON certificates.{$table}
                FOR EACH ROW
                EXECUTE FUNCTION certificates.reject_hard_delete()
            ");
        }

        DB::statement("
            CREATE OR REPLACE FUNCTION certificates.enforce_template_version_immutability()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.template_id IS DISTINCT FROM OLD.template_id
                    OR NEW.school_id IS DISTINCT FROM OLD.school_id
                    OR NEW.version_no IS DISTINCT FROM OLD.version_no
                    OR NEW.content_hash IS DISTINCT FROM OLD.content_hash
                    OR NEW.template_storage_key IS DISTINCT FROM OLD.template_storage_key
                    OR NEW.locale IS DISTINCT FROM OLD.locale THEN
                    RAISE EXCEPTION 'certificate_template_versions content/identity columns are immutable';
                END IF;

                RETURN NEW;
            END;
            $$
        ");

        DB::statement('
            CREATE TRIGGER certificate_template_versions_immutable_bu
            BEFORE UPDATE ON certificates.certificate_template_versions
            FOR EACH ROW
            EXECUTE FUNCTION certificates.enforce_template_version_immutability()
        ');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS certificate_template_versions_immutable_bu ON certificates.certificate_template_versions');
        DB::statement('DROP FUNCTION IF EXISTS certificates.enforce_template_version_immutability()');

        $rejectDeleteTables = [
            'certificate_templates',
            'certificate_template_versions',
            'certificates',
            'certificate_issuances',
            'certificate_artifacts',
            'certificate_generation_jobs',
        ];

        foreach ($rejectDeleteTables as $table) {
            DB::statement("DROP TRIGGER IF EXISTS {$table}_reject_delete ON certificates.{$table}");
        }

        DB::statement('DROP FUNCTION IF EXISTS certificates.reject_hard_delete()');
    }
};
