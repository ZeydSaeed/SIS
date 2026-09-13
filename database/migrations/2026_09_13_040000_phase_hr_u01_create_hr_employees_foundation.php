<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase HR-U01 — hr.job_positions, hr.employees, hr.employee_schools + FORCE RLS.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('CREATE SCHEMA IF NOT EXISTS hr');

        DB::statement("
            CREATE TABLE IF NOT EXISTS hr.job_positions (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id BIGINT NOT NULL,
                code VARCHAR(20) NOT NULL,
                name VARCHAR(255) NOT NULL,
                category SMALLINT NOT NULL DEFAULT 9,
                status SMALLINT NOT NULL DEFAULT 1,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT hr_job_positions_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT hr_job_positions_category_check
                    CHECK (category IN (1, 2, 3, 4, 9)),
                CONSTRAINT hr_job_positions_status_check
                    CHECK (status IN (1, 2)),
                CONSTRAINT hr_job_positions_school_code_uq
                    UNIQUE (school_id, code)
            )
        ");
        DB::statement('CREATE INDEX IF NOT EXISTS hr_job_positions_school_idx ON hr.job_positions (school_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS hr_job_positions_school_status_idx ON hr.job_positions (school_id, status)');

        DB::statement("
            CREATE TABLE IF NOT EXISTS hr.employees (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                employee_number VARCHAR(50) NOT NULL,
                user_id BIGINT,
                teacher_id BIGINT,
                national_id VARCHAR(20),
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                full_name VARCHAR(255) NOT NULL,
                hire_date DATE,
                status SMALLINT NOT NULL DEFAULT 1,
                effective_from TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                effective_to TIMESTAMPTZ,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT hr_employees_user_fk
                    FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL,
                CONSTRAINT hr_employees_teacher_fk
                    FOREIGN KEY (teacher_id) REFERENCES teachers.teachers(id) ON DELETE SET NULL,
                CONSTRAINT hr_employees_number_uq UNIQUE (employee_number),
                CONSTRAINT hr_employees_teacher_uq UNIQUE (teacher_id),
                CONSTRAINT hr_employees_status_check CHECK (status IN (1, 2))
            )
        ");
        DB::statement('CREATE INDEX IF NOT EXISTS hr_employees_status_idx ON hr.employees (status)');
        DB::statement('CREATE INDEX IF NOT EXISTS hr_employees_user_idx ON hr.employees (user_id)');

        DB::statement("
            CREATE TABLE IF NOT EXISTS hr.employee_schools (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                employee_id BIGINT NOT NULL,
                school_id BIGINT NOT NULL,
                academic_year_id BIGINT NOT NULL,
                job_position_id BIGINT,
                is_primary BOOLEAN NOT NULL DEFAULT true,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT hr_employee_schools_employee_fk
                    FOREIGN KEY (employee_id) REFERENCES hr.employees(id) ON DELETE RESTRICT,
                CONSTRAINT hr_employee_schools_school_fk
                    FOREIGN KEY (school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT hr_employee_schools_year_fk
                    FOREIGN KEY (academic_year_id) REFERENCES academic.academic_years(id) ON DELETE RESTRICT,
                CONSTRAINT hr_employee_schools_position_fk
                    FOREIGN KEY (job_position_id) REFERENCES hr.job_positions(id) ON DELETE SET NULL,
                CONSTRAINT hr_employee_schools_member_uq
                    UNIQUE (employee_id, school_id, academic_year_id)
            )
        ");
        DB::statement('CREATE INDEX IF NOT EXISTS hr_employee_schools_school_idx ON hr.employee_schools (school_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS hr_employee_schools_employee_idx ON hr.employee_schools (employee_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS hr_employee_schools_school_year_idx ON hr.employee_schools (school_id, academic_year_id)');

        DB::statement('ALTER TABLE hr.job_positions ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE hr.job_positions FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS job_positions_school_isolation ON hr.job_positions');
        DB::statement("
            CREATE POLICY job_positions_school_isolation ON hr.job_positions
            USING (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
            )
            WITH CHECK (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
            )
        ");

        DB::statement('ALTER TABLE hr.employee_schools ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE hr.employee_schools FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS employee_schools_school_isolation ON hr.employee_schools');
        DB::statement("
            CREATE POLICY employee_schools_school_isolation ON hr.employee_schools
            USING (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
            )
            WITH CHECK (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
            )
        ");

        DB::statement('ALTER TABLE hr.employees ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE hr.employees FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS employees_body_school_isolation ON hr.employees');
        DB::statement("
            CREATE POLICY employees_body_school_isolation ON hr.employees
            USING (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND EXISTS (
                    SELECT 1 FROM hr.employee_schools es
                    WHERE es.employee_id = employees.id
                      AND es.school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
                )
            )
            WITH CHECK (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
            )
        ");

        foreach (['job_positions', 'employees', 'employee_schools'] as $table) {
            DB::statement("
                CREATE OR REPLACE FUNCTION hr.reject_{$table}_delete()
                RETURNS trigger LANGUAGE plpgsql AS $$
                BEGIN
                    RAISE EXCEPTION 'Hard delete of hr.{$table} is forbidden';
                END; $$
            ");
            DB::statement("DROP TRIGGER IF EXISTS {$table}_reject_delete ON hr.{$table}");
            DB::statement("
                CREATE TRIGGER {$table}_reject_delete
                BEFORE DELETE ON hr.{$table}
                FOR EACH ROW EXECUTE FUNCTION hr.reject_{$table}_delete()
            ");
        }
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        foreach (['job_positions', 'employees', 'employee_schools'] as $table) {
            DB::statement("DROP TRIGGER IF EXISTS {$table}_reject_delete ON hr.{$table}");
            DB::statement("DROP FUNCTION IF EXISTS hr.reject_{$table}_delete()");
        }

        DB::statement('DROP POLICY IF EXISTS employees_body_school_isolation ON hr.employees');
        DB::statement('DROP POLICY IF EXISTS employee_schools_school_isolation ON hr.employee_schools');
        DB::statement('DROP POLICY IF EXISTS job_positions_school_isolation ON hr.job_positions');
        DB::statement('DROP TABLE IF EXISTS hr.employee_schools');
        DB::statement('DROP TABLE IF EXISTS hr.employees');
        DB::statement('DROP TABLE IF EXISTS hr.job_positions');
    }
};
