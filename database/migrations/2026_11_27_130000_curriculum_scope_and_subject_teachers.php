<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «المناهج» (owner, 2026-11-27): a curriculum belongs to a branch (الفرع), a specialization (الاختصاص), a class (الصف)
 * and a section (الشعبة); each of its subjects gets one teacher, or a main teacher plus one or more assistants.
 *
 * curriculum.curricula (additive, nullable — existing API callers keep working):
 *   + branch_id → organization.branches, class_id → enrollment.classes, section_id → enrollment.sections (restrict).
 *   CHECK section ⇒ class + branch. Trigger: branch of the school, department of the branch, class of the school /
 *   year / grade level, section of the class. Partial UNIQUE: one active curriculum per (school, year, branch,
 *   department, section). branch_id is backfilled from the department.
 *
 * teachers.teaching_assignments (the one record of who teaches what where — grade entry, question bank and the
 * timetable already read it):
 *   + curriculum_subject_id → curriculum.curriculum_subjects (restrict, nullable — NULL = set on «المعلمون»),
 *   + role SMALLINT (1 مدرس المادة / الرئيسي, 2 مدرس مساعد). Partial UNIQUE: one active main teacher per line.
 *   Trigger: a line-bound row has the line's subject and the curriculum's school / year / branch / department /
 *   class / section.
 */
return new class extends Migration
{
    public function up(): void
    {
        $curricula = SchemaHelper::qualified('curriculum', 'curricula');
        $assignments = SchemaHelper::qualified('teachers', 'teaching_assignments');

        Schema::table($curricula, function (Blueprint $table) use ($curricula): void {
            if (! Schema::hasColumn($curricula, 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('department_id')
                    ->constrained(SchemaHelper::qualified('organization', 'branches'))->restrictOnDelete();
            }
            if (! Schema::hasColumn($curricula, 'class_id')) {
                $table->foreignId('class_id')->nullable()->after('branch_id')
                    ->constrained(SchemaHelper::qualified('enrollment', 'classes'))->restrictOnDelete();
            }
            if (! Schema::hasColumn($curricula, 'section_id')) {
                $table->foreignId('section_id')->nullable()->after('class_id')
                    ->constrained(SchemaHelper::qualified('enrollment', 'sections'))->restrictOnDelete();
            }
        });

        Schema::table($assignments, function (Blueprint $table) use ($assignments): void {
            if (! Schema::hasColumn($assignments, 'curriculum_subject_id')) {
                $table->foreignId('curriculum_subject_id')->nullable()->after('section_id')
                    ->constrained(SchemaHelper::qualified('curriculum', 'curriculum_subjects'))->restrictOnDelete();
            }
            if (! Schema::hasColumn($assignments, 'role')) {
                $table->smallInteger('role')->default(1)->after('curriculum_subject_id');
            }
        });

        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        // Existing curricula: the branch is the department's.
        DB::statement("
            UPDATE {$curricula} AS c
            SET branch_id = d.branch_id
            FROM organization.departments AS d
            WHERE d.id = c.department_id AND c.branch_id IS NULL AND d.branch_id IS NOT NULL
        ");

        DB::statement("ALTER TABLE {$curricula} DROP CONSTRAINT IF EXISTS curricula_section_needs_class_branch");
        DB::statement("ALTER TABLE {$curricula} ADD CONSTRAINT curricula_section_needs_class_branch CHECK (section_id IS NULL OR (class_id IS NOT NULL AND branch_id IS NOT NULL))");
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS curricula_active_scope_unique
            ON {$curricula} (school_id, academic_year_id, branch_id, COALESCE(department_id, 0), section_id)
            WHERE status = 1 AND section_id IS NOT NULL
        ");

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION curriculum.check_curricula_scope()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.branch_id IS NOT NULL AND NOT EXISTS (
                    SELECT 1 FROM organization.branches b WHERE b.id = NEW.branch_id AND b.school_id = NEW.school_id
                ) THEN
                    RAISE EXCEPTION 'curriculum.curricula: branch % is not a branch of school %', NEW.branch_id, NEW.school_id
                        USING ERRCODE = 'check_violation';
                END IF;
                IF NEW.department_id IS NOT NULL AND NEW.branch_id IS NOT NULL AND NOT EXISTS (
                    SELECT 1 FROM organization.departments d WHERE d.id = NEW.department_id AND d.branch_id = NEW.branch_id
                ) THEN
                    RAISE EXCEPTION 'curriculum.curricula: department % is not in branch %', NEW.department_id, NEW.branch_id
                        USING ERRCODE = 'check_violation';
                END IF;
                IF NEW.class_id IS NOT NULL AND NOT EXISTS (
                    SELECT 1 FROM enrollment.classes k
                    WHERE k.id = NEW.class_id AND k.school_id = NEW.school_id
                      AND k.academic_year_id = NEW.academic_year_id AND k.grade_level_id = NEW.grade_level_id
                ) THEN
                    RAISE EXCEPTION 'curriculum.curricula: class % does not match the school, year and grade level', NEW.class_id
                        USING ERRCODE = 'check_violation';
                END IF;
                IF NEW.section_id IS NOT NULL AND NOT EXISTS (
                    SELECT 1 FROM enrollment.sections s WHERE s.id = NEW.section_id AND s.class_id = NEW.class_id
                ) THEN
                    RAISE EXCEPTION 'curriculum.curricula: section % is not in class %', NEW.section_id, NEW.class_id
                        USING ERRCODE = 'check_violation';
                END IF;
                RETURN NEW;
            END; $$
        SQL);
        DB::statement('DROP TRIGGER IF EXISTS curricula_scope_check ON curriculum.curricula');
        DB::statement('
            CREATE TRIGGER curricula_scope_check
            BEFORE INSERT OR UPDATE OF school_id, academic_year_id, grade_level_id, branch_id, department_id, class_id, section_id
            ON curriculum.curricula FOR EACH ROW EXECUTE FUNCTION curriculum.check_curricula_scope()
        ');

        DB::statement("ALTER TABLE {$assignments} DROP CONSTRAINT IF EXISTS teaching_assignments_role_check");
        DB::statement("ALTER TABLE {$assignments} ADD CONSTRAINT teaching_assignments_role_check CHECK (role IN (1, 2))");
        // The curriculum page lists a curriculum's teachers by line; «حذف المنهج / المادة» deletes by line.
        DB::statement("
            CREATE INDEX IF NOT EXISTS teaching_assignments_curriculum_subject_idx
            ON {$assignments} (curriculum_subject_id) WHERE curriculum_subject_id IS NOT NULL
        ");
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS teaching_assignments_line_main_unique
            ON {$assignments} (curriculum_subject_id)
            WHERE status = 1 AND role = 1 AND curriculum_subject_id IS NOT NULL
        ");

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION teachers.check_teaching_assignment_line()
            RETURNS trigger LANGUAGE plpgsql AS $$
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
                RETURN NEW;
            END; $$
        SQL);
        DB::statement('DROP TRIGGER IF EXISTS teaching_assignments_line_check ON teachers.teaching_assignments');
        DB::statement('
            CREATE TRIGGER teaching_assignments_line_check
            BEFORE INSERT OR UPDATE OF curriculum_subject_id, subject_id, school_id, academic_year_id, branch_id, department_id, class_id, section_id
            ON teachers.teaching_assignments FOR EACH ROW EXECUTE FUNCTION teachers.check_teaching_assignment_line()
        ');
    }

    public function down(): void
    {
        $curricula = SchemaHelper::qualified('curriculum', 'curricula');
        $assignments = SchemaHelper::qualified('teachers', 'teaching_assignments');

        if (SchemaHelper::isPostgreSql()) {
            DB::statement('DROP TRIGGER IF EXISTS teaching_assignments_line_check ON teachers.teaching_assignments');
            DB::statement('DROP FUNCTION IF EXISTS teachers.check_teaching_assignment_line()');
            DB::statement('DROP INDEX IF EXISTS teachers.teaching_assignments_line_main_unique');
            DB::statement('DROP INDEX IF EXISTS teachers.teaching_assignments_curriculum_subject_idx');
            DB::statement("ALTER TABLE {$assignments} DROP CONSTRAINT IF EXISTS teaching_assignments_role_check");
            DB::statement('DROP TRIGGER IF EXISTS curricula_scope_check ON curriculum.curricula');
            DB::statement('DROP FUNCTION IF EXISTS curriculum.check_curricula_scope()');
            DB::statement('DROP INDEX IF EXISTS curriculum.curricula_active_scope_unique');
            DB::statement("ALTER TABLE {$curricula} DROP CONSTRAINT IF EXISTS curricula_section_needs_class_branch");
        }

        Schema::table($assignments, function (Blueprint $table) use ($assignments): void {
            if (Schema::hasColumn($assignments, 'curriculum_subject_id')) {
                $table->dropConstrainedForeignId('curriculum_subject_id');
            }
            if (Schema::hasColumn($assignments, 'role')) {
                $table->dropColumn('role');
            }
        });

        Schema::table($curricula, function (Blueprint $table) use ($curricula): void {
            foreach (['section_id', 'class_id', 'branch_id'] as $column) {
                if (Schema::hasColumn($curricula, $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
        });
    }
};
