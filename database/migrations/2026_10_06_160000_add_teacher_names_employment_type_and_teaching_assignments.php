<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «المعلمون» page — who teaches what, where.
 *
 * - teachers.teachers: father_name / grandfather_name (nullable) → الاسم الثلاثي واللقب.
 *   specialization_field stays the certificate specialization (تخصص الشهادة).
 * - teachers.teacher_schools.employment_type (SMALLINT, nullable): نوع التعيين per school/year —
 *   1 ملاك · 2 مكلف · 3 تنسيب · 4 محاضر · 5 عقد. A teacher can be ملاك in one school and
 *   تنسيب in another, so it lives on the membership, not on the teacher.
 * - teachers.teaching_assignments (new, blueprint object): teacher teaches a subject in a branch
 *   (الفرع), optionally a department (الاختصاص), class (الصف) and section (الشعبة), per school/year.
 *   Ended by status = 2 + effective_to (never hard-deleted). Partial UNIQUE on the active row.
 *   RLS school isolation like teacher_subjects. Index (school_id, academic_year_id) — roster read.
 */
return new class extends Migration
{
    public function up(): void
    {
        $teachers = SchemaHelper::qualified('teachers', 'teachers');
        $memberships = SchemaHelper::qualified('teachers', 'teacher_schools');
        $assignments = SchemaHelper::qualified('teachers', 'teaching_assignments');
        $pg = SchemaHelper::isPostgreSql();

        if (! Schema::hasColumn($teachers, 'father_name')) {
            Schema::table($teachers, function (Blueprint $table): void {
                $table->string('father_name', 100)->nullable()->after('first_name');
                $table->string('grandfather_name', 100)->nullable()->after('father_name');
            });
        }

        if (! Schema::hasColumn($memberships, 'employment_type')) {
            Schema::table($memberships, function (Blueprint $table): void {
                $table->smallInteger('employment_type')->nullable()->after('is_primary');
            });
        }

        if ($pg) {
            DB::statement("ALTER TABLE {$memberships} DROP CONSTRAINT IF EXISTS teacher_schools_employment_type_check");
            DB::statement("ALTER TABLE {$memberships} ADD CONSTRAINT teacher_schools_employment_type_check CHECK (employment_type IS NULL OR employment_type BETWEEN 1 AND 5)");
        }

        if (! Schema::hasTable($assignments)) {
            Schema::create($assignments, function (Blueprint $table): void {
                $table->id();
                $table->foreignId('teacher_id')->constrained(SchemaHelper::qualified('teachers', 'teachers'))->restrictOnDelete();
                $table->foreignId('school_id')->constrained(SchemaHelper::qualified('organization', 'schools'))->restrictOnDelete();
                $table->foreignId('academic_year_id')->constrained(SchemaHelper::qualified('academic', 'academic_years'))->restrictOnDelete();
                $table->foreignId('subject_id')->constrained(SchemaHelper::qualified('curriculum', 'subjects'))->restrictOnDelete();
                $table->foreignId('branch_id')->constrained(SchemaHelper::qualified('organization', 'branches'))->restrictOnDelete();
                $table->foreignId('department_id')->nullable()->constrained(SchemaHelper::qualified('organization', 'departments'))->restrictOnDelete();
                $table->foreignId('class_id')->nullable()->constrained(SchemaHelper::qualified('enrollment', 'classes'))->restrictOnDelete();
                $table->foreignId('section_id')->nullable()->constrained(SchemaHelper::qualified('enrollment', 'sections'))->restrictOnDelete();
                $table->smallInteger('status')->default(1);
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->timestamps();

                $table->index(['school_id', 'academic_year_id']);
            });
        }

        if (! $pg) {
            return;
        }

        DB::statement("ALTER TABLE {$assignments} ADD CONSTRAINT teaching_assignments_status_check CHECK (status IN (1, 2))");
        DB::statement("ALTER TABLE {$assignments} ADD CONSTRAINT teaching_assignments_section_needs_class CHECK (section_id IS NULL OR class_id IS NOT NULL)");
        DB::statement("ALTER TABLE {$assignments} ADD CONSTRAINT teaching_assignments_effective_check CHECK (effective_to IS NULL OR effective_to >= effective_from)");
        DB::statement("
            CREATE UNIQUE INDEX teaching_assignments_active_unique
            ON {$assignments} (teacher_id, academic_year_id, subject_id, branch_id,
                COALESCE(department_id, 0), COALESCE(class_id, 0), COALESCE(section_id, 0))
            WHERE status = 1
        ");

        DB::statement("ALTER TABLE {$assignments} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$assignments} FORCE ROW LEVEL SECURITY");
        DB::statement("
            CREATE POLICY teaching_assignments_school_isolation ON {$assignments}
            USING (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
            )
            WITH CHECK (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
            )
        ");

        DB::statement("
            CREATE OR REPLACE FUNCTION teachers.reject_teaching_assignments_delete()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Hard delete of teachers.teaching_assignments is forbidden — end it (status 2)'
                    USING ERRCODE = 'check_violation';
            END; $$
        ");
        DB::statement("
            CREATE TRIGGER teaching_assignments_reject_delete
            BEFORE DELETE ON {$assignments}
            FOR EACH ROW
            EXECUTE FUNCTION teachers.reject_teaching_assignments_delete()
        ");
    }

    public function down(): void
    {
        $teachers = SchemaHelper::qualified('teachers', 'teachers');
        $memberships = SchemaHelper::qualified('teachers', 'teacher_schools');

        if (SchemaHelper::isPostgreSql()) {
            DB::statement('DROP TRIGGER IF EXISTS teaching_assignments_reject_delete ON teachers.teaching_assignments');
            DB::statement('DROP FUNCTION IF EXISTS teachers.reject_teaching_assignments_delete()');
            DB::statement("ALTER TABLE {$memberships} DROP CONSTRAINT IF EXISTS teacher_schools_employment_type_check");
        }

        Schema::dropIfExists(SchemaHelper::qualified('teachers', 'teaching_assignments'));

        if (Schema::hasColumn($memberships, 'employment_type')) {
            Schema::table($memberships, fn (Blueprint $table) => $table->dropColumn('employment_type'));
        }

        if (Schema::hasColumn($teachers, 'father_name')) {
            Schema::table($teachers, fn (Blueprint $table) => $table->dropColumn(['father_name', 'grandfather_name']));
        }
    }
};
