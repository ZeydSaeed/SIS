<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8.1-U03 — Soft-void support on teacher_qualifications (status + effective_to).
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = SchemaHelper::qualified('teachers', 'teacher_qualifications');

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->smallInteger('status')->default(1);
            $blueprint->timestampTz('effective_from')->nullable();
            $blueprint->timestampTz('effective_to')->nullable();
            $blueprint->index(['teacher_id', 'status'], 'teacher_qualifications_teacher_status_idx');
        });

        if (SchemaHelper::isPostgreSql()) {
            DB::statement("
                UPDATE {$table}
                SET status = 1,
                    effective_from = COALESCE(effective_from, created_at)
                WHERE effective_from IS NULL
            ");
            DB::statement("ALTER TABLE {$table} ALTER COLUMN status SET NOT NULL");
            DB::statement("ALTER TABLE {$table} ALTER COLUMN effective_from SET NOT NULL");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT teacher_qualifications_status_chk CHECK (status IN (1, 2))");
        } else {
            DB::table($table)->whereNull('effective_from')->update([
                'status' => 1,
                'effective_from' => DB::raw('created_at'),
            ]);
        }
    }

    public function down(): void
    {
        $table = SchemaHelper::qualified('teachers', 'teacher_qualifications');

        if (SchemaHelper::isPostgreSql()) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS teacher_qualifications_status_chk");
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropIndex('teacher_qualifications_teacher_status_idx');
            $blueprint->dropColumn(['status', 'effective_from', 'effective_to']);
        });
    }
};
