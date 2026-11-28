<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «المعلمون» (owner, 2026-11-28):
 *  - degree SMALLINT NULL, CHECK 1–6 — «الشهادة الحاصل عليها» (TeacherDegree: إعدادية، دبلوم، بكالوريوس،
 *    دبلوم عالي، ماجستير، دكتوراه); NULL = not stated.
 *  - access_locked BOOLEAN NOT NULL DEFAULT false — «قفل»: the teacher may not sign in through the link
 *    (stored now; the teacher link sign-in reads it when it exists).
 */
return new class extends Migration
{
    public function up(): void
    {
        $teachers = SchemaHelper::qualified('teachers', 'teachers');
        Schema::table($teachers, function (Blueprint $table) use ($teachers): void {
            if (! Schema::hasColumn($teachers, 'degree')) {
                $table->smallInteger('degree')->nullable()->after('specialization_field');
            }
            if (! Schema::hasColumn($teachers, 'access_locked')) {
                $table->boolean('access_locked')->default(false)->after('status');
            }
        });
        if (SchemaHelper::isPostgreSql()) {
            DB::statement("ALTER TABLE {$teachers} DROP CONSTRAINT IF EXISTS teachers_degree_check");
            DB::statement("ALTER TABLE {$teachers} ADD CONSTRAINT teachers_degree_check CHECK (degree IS NULL OR degree BETWEEN 1 AND 6)");
        }
    }

    public function down(): void
    {
        $teachers = SchemaHelper::qualified('teachers', 'teachers');
        if (SchemaHelper::isPostgreSql()) {
            DB::statement("ALTER TABLE {$teachers} DROP CONSTRAINT IF EXISTS teachers_degree_check");
        }
        foreach (['degree', 'access_locked'] as $column) {
            if (Schema::hasColumn($teachers, $column)) {
                Schema::table($teachers, fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
