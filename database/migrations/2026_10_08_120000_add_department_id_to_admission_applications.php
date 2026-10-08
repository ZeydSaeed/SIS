<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * An application named its placement only by text (branch_name / department_name), matched against the catalogue
 * by name on every read and at conversion — so renaming a department silently detached its applications.
 * Students (students.students.department_id) already reference the department; the application now does too.
 *
 * Additive: department_id nullable (an application may be a draft without a placement), FK restrict.
 * Backfill: the department of the application's school whose trimmed name equals department_name — the one in the
 * application's branch when branch_id is set. The text columns stay as "as applied" snapshot.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('ALTER TABLE admission.applications ADD COLUMN department_id BIGINT NULL
            REFERENCES organization.departments (id) ON DELETE RESTRICT');
        DB::statement('CREATE INDEX admission_applications_department_id_idx ON admission.applications (department_id) WHERE department_id IS NOT NULL');

        DB::statement("
            UPDATE admission.applications a
            SET department_id = (
                SELECT d.id FROM organization.departments d
                WHERE d.school_id = a.school_id
                  AND btrim(d.name) = btrim(a.department_name)
                  AND (a.branch_id IS NULL OR d.branch_id = a.branch_id)
                ORDER BY d.status, d.id
                LIMIT 1
            )
            WHERE a.department_id IS NULL AND a.department_name IS NOT NULL AND btrim(a.department_name) <> ''
        ");
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS admission.admission_applications_department_id_idx');
        DB::statement('ALTER TABLE admission.applications DROP COLUMN IF EXISTS department_id');
    }
};
