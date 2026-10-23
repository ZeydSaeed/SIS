<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EXAMIS U10 — exam types (and their weights, which drive term results) belong to one school.
 *
 * Additive: school_id NOT NULL + FK; UNIQUE (school_id, code) replaces UNIQUE (code); UNIQUE (id, school_id) backs the
 * composite FK exams (exam_type_id, school_id) → exam_types (id, school_id), so an exam can only use a type of its own
 * school; fail-closed RLS + FORCE like the other exam tables.
 *
 * Existing rows are assigned to the one school whose exams use them. A type used by several schools, or used by none,
 * stops the migration (nothing is guessed, nothing is deleted) — split or assign it by hand first.
 */
return new class extends Migration
{
    public function up(): void
    {
        $types = SchemaHelper::qualified('exams', 'exam_types');
        $exams = SchemaHelper::qualified('exams', 'exams');

        Schema::table($types, function (Blueprint $table): void {
            $table->foreignId('school_id')->nullable()
                ->constrained(SchemaHelper::qualified('organization', 'schools'))
                ->restrictOnDelete();
        });

        $shared = DB::table($exams)->groupBy('exam_type_id')->havingRaw('COUNT(DISTINCT school_id) > 1')->pluck('exam_type_id')->all();
        if ($shared !== []) {
            throw new RuntimeException('exams.exam_types used by more than one school (ids '.implode(',', $shared).'): split them per school before this migration.');
        }

        foreach (DB::table($exams)->select('exam_type_id', DB::raw('MIN(school_id) AS school_id'))->groupBy('exam_type_id')->get() as $use) {
            DB::table($types)->where('id', $use->exam_type_id)->update(['school_id' => $use->school_id]);
        }

        $unowned = DB::table($types)->whereNull('school_id')->pluck('code')->all();
        if ($unowned !== []) {
            throw new RuntimeException('exams.exam_types not used by any exam have no school (codes '.implode(',', $unowned).'): assign school_id by hand before this migration.');
        }

        Schema::table($types, function (Blueprint $table): void {
            $table->dropUnique(['code']);
        });

        DB::statement('ALTER TABLE exams.exam_types ALTER COLUMN school_id SET NOT NULL');
        DB::statement('ALTER TABLE exams.exam_types ADD CONSTRAINT exam_types_school_code_unique UNIQUE (school_id, code)');
        DB::statement('ALTER TABLE exams.exam_types ADD CONSTRAINT exam_types_id_school_unique UNIQUE (id, school_id)');
        DB::statement('ALTER TABLE exams.exams ADD CONSTRAINT exams_exam_type_same_school_fk
            FOREIGN KEY (exam_type_id, school_id) REFERENCES exams.exam_types (id, school_id) ON DELETE RESTRICT');

        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('ALTER TABLE exams.exam_types ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE exams.exam_types FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY exam_types_school_isolation ON exams.exam_types
            USING (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
            )
        ");
    }

    public function down(): void
    {
        if (SchemaHelper::isPostgreSql()) {
            DB::statement('DROP POLICY IF EXISTS exam_types_school_isolation ON exams.exam_types');
            DB::statement('ALTER TABLE exams.exam_types NO FORCE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE exams.exam_types DISABLE ROW LEVEL SECURITY');
        }

        DB::statement('ALTER TABLE exams.exams DROP CONSTRAINT IF EXISTS exams_exam_type_same_school_fk');
        DB::statement('ALTER TABLE exams.exam_types DROP CONSTRAINT IF EXISTS exam_types_id_school_unique');
        DB::statement('ALTER TABLE exams.exam_types DROP CONSTRAINT IF EXISTS exam_types_school_code_unique');

        // Fails if two schools now share a code — the global catalogue cannot hold both.
        Schema::table(SchemaHelper::qualified('exams', 'exam_types'), function (Blueprint $table): void {
            $table->unique(['code']);
            $table->dropConstrainedForeignId('school_id');
        });
    }
};
