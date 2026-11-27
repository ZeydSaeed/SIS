<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The section belongs to the teacher, not to the curriculum (owner, 2026-11-27): a curriculum is for a branch /
 * specialization / class; each of its subjects is taught per section — «اللغة العربية» by one teacher in section A and
 * another in B, or one teacher for several / all sections — by one or more main teachers and one or more assistants.
 *
 * - teachers.teaching_assignments: teaching_assignments_line_main_unique dropped (several main teachers allowed).
 *   The line trigger: a line-bound row's section is the curriculum's section when the curriculum names one, else NULL
 *   (all sections) or a section of the curriculum's class. The part rule (2026_11_27_160000) stays.
 * - curriculum.curricula: partial UNIQUE — one active class-level curriculum (no section) per
 *   (school, year, branch, department, class). curricula.section_id stays (nullable) for curricula that name one.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }
        $curricula = SchemaHelper::qualified('curriculum', 'curricula');

        DB::statement('DROP INDEX IF EXISTS teachers.teaching_assignments_line_main_unique');
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS curricula_active_class_scope_unique
            ON {$curricula} (school_id, academic_year_id, branch_id, COALESCE(department_id, 0), class_id)
            WHERE status = 1 AND section_id IS NULL AND class_id IS NOT NULL
        ");
        $this->lineCheck(sectionPerTeacher: true);
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }
        $assignments = SchemaHelper::qualified('teachers', 'teaching_assignments');

        $this->lineCheck(sectionPerTeacher: false);
        DB::statement('DROP INDEX IF EXISTS curriculum.curricula_active_class_scope_unique');
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS teaching_assignments_line_main_unique
            ON {$assignments} (curriculum_subject_id, COALESCE(subject_type, 0))
            WHERE status = 1 AND role = 1 AND curriculum_subject_id IS NOT NULL
        ");
    }

    private function lineCheck(bool $sectionPerTeacher): void
    {
        $section = $sectionPerTeacher
            ? "AND (CASE WHEN c.section_id IS NOT NULL THEN NEW.section_id IS NOT DISTINCT FROM c.section_id
                    ELSE NEW.section_id IS NULL OR EXISTS (SELECT 1 FROM enrollment.sections s WHERE s.id = NEW.section_id AND s.class_id = c.class_id) END)"
            : 'AND c.section_id IS NOT DISTINCT FROM NEW.section_id';

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
                      {$section}
                ) THEN
                    RAISE EXCEPTION 'teachers.teaching_assignments: row does not match curriculum line %', NEW.curriculum_subject_id
                        USING ERRCODE = 'check_violation';
                END IF;
                IF NEW.curriculum_subject_id IS NOT NULL AND NEW.subject_type IS NOT NULL AND NOT EXISTS (
                    SELECT 1 FROM curriculum.subject_types st WHERE st.subject_id = NEW.subject_id AND st.subject_type = NEW.subject_type
                ) THEN
                    RAISE EXCEPTION 'teachers.teaching_assignments: part % is not a type of subject %', NEW.subject_type, NEW.subject_id
                        USING ERRCODE = 'check_violation';
                END IF;
                RETURN NEW;
            END; \$\$
        SQL);
    }
};
