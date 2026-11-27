<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «نوع المادة» with hours per type (owner, 2026-11-27): عملي 2 + نظري 3 …
 *
 * curriculum.subject_types + hours SMALLINT NULL (CHECK 0–40; NULL = not given). When a subject's types carry hours,
 * curriculum.subjects.credit_hours is their total (kept by the application in the same transaction).
 * Backfill: a subject with one type gets its credit_hours on that type.
 */
return new class extends Migration
{
    public function up(): void
    {
        $types = SchemaHelper::qualified('curriculum', 'subject_types');
        if (! Schema::hasColumn($types, 'hours')) {
            Schema::table($types, function (Blueprint $table): void {
                $table->smallInteger('hours')->nullable()->after('subject_type');
            });
        }

        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement("ALTER TABLE {$types} DROP CONSTRAINT IF EXISTS subject_types_hours_check");
        DB::statement("ALTER TABLE {$types} ADD CONSTRAINT subject_types_hours_check CHECK (hours IS NULL OR hours BETWEEN 0 AND 40)");
        DB::statement("
            UPDATE {$types} AS st
            SET hours = s.credit_hours
            FROM curriculum.subjects AS s
            WHERE s.id = st.subject_id
              AND st.hours IS NULL
              AND s.credit_hours IS NOT NULL
              AND (SELECT count(*) FROM {$types} x WHERE x.subject_id = st.subject_id) = 1
        ");
    }

    public function down(): void
    {
        $types = SchemaHelper::qualified('curriculum', 'subject_types');
        if (SchemaHelper::isPostgreSql()) {
            DB::statement("ALTER TABLE {$types} DROP CONSTRAINT IF EXISTS subject_types_hours_check");
        }
        if (Schema::hasColumn($types, 'hours')) {
            Schema::table($types, fn (Blueprint $table) => $table->dropColumn('hours'));
        }
    }
};
