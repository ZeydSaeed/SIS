<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A teacher may teach one part of a subject (owner, 2026-11-27): «نظري» to one teacher and «عملي» to another, each
 * part with its main teacher and assistants.
 *
 * teachers.teaching_assignments + subject_type SMALLINT NULL (CHECK 1–4) — the part taught; NULL = the whole subject.
 * - teaching_assignments_active_unique gains COALESCE(subject_type, 0) (one teacher may teach both parts).
 * - teaching_assignments_line_main_unique becomes one active main teacher per (line, part).
 * - trigger teaching_assignments_line_check: the part is one of the subject's types (curriculum.subject_types).
 */
return new class extends Migration
{
    public function up(): void
    {
        $assignments = SchemaHelper::qualified('teachers', 'teaching_assignments');
        if (! Schema::hasColumn($assignments, 'subject_type')) {
            Schema::table($assignments, function (Blueprint $table): void {
                $table->smallInteger('subject_type')->nullable()->after('role');
            });
        }

        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement("ALTER TABLE {$assignments} DROP CONSTRAINT IF EXISTS teaching_assignments_subject_type_check");
        DB::statement("ALTER TABLE {$assignments} ADD CONSTRAINT teaching_assignments_subject_type_check CHECK (subject_type IS NULL OR subject_type BETWEEN 1 AND 4)");

        DB::statement('DROP INDEX IF EXISTS teachers.teaching_assignments_active_unique');
        DB::statement("
            CREATE UNIQUE INDEX teaching_assignments_active_unique
            ON {$assignments} (teacher_id, academic_year_id, subject_id, branch_id,
                COALESCE(department_id, 0), COALESCE(class_id, 0), COALESCE(section_id, 0), COALESCE(subject_type, 0))
            WHERE status = 1
        ");
        DB::statement('DROP INDEX IF EXISTS teachers.teaching_assignments_line_main_unique');
        DB::statement("
            CREATE UNIQUE INDEX teaching_assignments_line_main_unique
            ON {$assignments} (curriculum_subject_id, COALESCE(subject_type, 0))
            WHERE status = 1 AND role = 1 AND curriculum_subject_id IS NOT NULL
        ");

        $this->lineCheck(withPart: true);
    }

    public function down(): void
    {
        $assignments = SchemaHelper::qualified('teachers', 'teaching_assignments');
        if (SchemaHelper::isPostgreSql()) {
            $this->lineCheck(withPart: false);
            DB::statement('DROP INDEX IF EXISTS teachers.teaching_assignments_line_main_unique');
            DB::statement("
                CREATE UNIQUE INDEX teaching_assignments_line_main_unique
                ON {$assignments} (curriculum_subject_id)
                WHERE status = 1 AND role = 1 AND curriculum_subject_id IS NOT NULL
            ");
            DB::statement('DROP INDEX IF EXISTS teachers.teaching_assignments_active_unique');
            DB::statement("
                CREATE UNIQUE INDEX teaching_assignments_active_unique
                ON {$assignments} (teacher_id, academic_year_id, subject_id, branch_id,
                    COALESCE(department_id, 0), COALESCE(class_id, 0), COALESCE(section_id, 0))
                WHERE status = 1
            ");
            DB::statement("ALTER TABLE {$assignments} DROP CONSTRAINT IF EXISTS teaching_assignments_subject_type_check");
        }
        if (Schema::hasColumn($assignments, 'subject_type')) {
            Schema::table($assignments, fn (Blueprint $table) => $table->dropColumn('subject_type'));
        }
    }

    /** The line-consistency trigger (2026_11_27_130000), with or without the «part is a type of the subject» rule. */
    private function lineCheck(bool $withPart): void
    {
        $part = $withPart
            ? "AND (NEW.subject_type IS NULL OR EXISTS (SELECT 1 FROM curriculum.subject_types st WHERE st.subject_id = NEW.subject_id AND st.subject_type = NEW.subject_type))"
            : '';
        $columns = $withPart ? ', subject_type' : '';

        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION teachers.check_teaching_assignment_line()
            RETURNS trigger LANGUAGE plpgsql AS \$\$
            BEGIN
                IF NEW.curriculum_subject_id IS NOT NULL AND NOT EXISTS (
                    SELECT 1
                    FROM curriculum.curriculum_subjects cs
                    JOIN curriculum.curricula c ON c.id = cs.curriculum_id
                    WHERE cs.id = NEW.curriculum_subject_id
                      AND cs.subject_id = NEW.subject_id
                      AND c.school_id = NEW.school_id
                      AND c.academic_year_id = NEW.academic_year_id
                      AND c.branch_id IS NOT DISTINCT FROM NEW.branch_id
                      AND c.department_id IS NOT DISTINCT FROM NEW.department_id
                      AND c.class_id IS NOT DISTINCT FROM NEW.class_id
                      AND c.section_id IS NOT DISTINCT FROM NEW.section_id
                ) THEN
                    RAISE EXCEPTION 'teachers.teaching_assignments: row does not match curriculum line %', NEW.curriculum_subject_id
                        USING ERRCODE = 'check_violation';
                END IF;
                IF NEW.curriculum_subject_id IS NOT NULL AND NOT (TRUE {$part}) THEN
                    RAISE EXCEPTION 'teachers.teaching_assignments: part % is not a type of subject %', NEW.subject_type, NEW.subject_id
                        USING ERRCODE = 'check_violation';
                END IF;
                RETURN NEW;
            END; \$\$
        SQL);
        DB::statement('DROP TRIGGER IF EXISTS teaching_assignments_line_check ON teachers.teaching_assignments');
        DB::statement("
            CREATE TRIGGER teaching_assignments_line_check
            BEFORE INSERT OR UPDATE OF curriculum_subject_id, subject_id, school_id, academic_year_id, branch_id, department_id, class_id, section_id{$columns}
            ON teachers.teaching_assignments FOR EACH ROW EXECUTE FUNCTION teachers.check_teaching_assignment_line()
        ");
    }
};
