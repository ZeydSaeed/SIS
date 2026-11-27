<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Curriculum plan hours split: «نظري» (theory_hours) + «تطبيقي/عملي» (practical_hours) per subject in a curriculum.
 * weekly_hours stays the total the timetable reads (theory + practical when the split is given).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('ALTER TABLE curriculum.curriculum_subjects ADD COLUMN IF NOT EXISTS theory_hours SMALLINT NULL');
        DB::statement('ALTER TABLE curriculum.curriculum_subjects ADD COLUMN IF NOT EXISTS practical_hours SMALLINT NULL');
        DB::statement('ALTER TABLE curriculum.curriculum_subjects DROP CONSTRAINT IF EXISTS curriculum_subjects_hours_split_check');
        DB::statement('
            ALTER TABLE curriculum.curriculum_subjects
            ADD CONSTRAINT curriculum_subjects_hours_split_check
            CHECK (
                (theory_hours IS NULL OR theory_hours BETWEEN 0 AND 40)
                AND (practical_hours IS NULL OR practical_hours BETWEEN 0 AND 40)
            )
        ');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('ALTER TABLE curriculum.curriculum_subjects DROP CONSTRAINT IF EXISTS curriculum_subjects_hours_split_check');
        DB::statement('ALTER TABLE curriculum.curriculum_subjects DROP COLUMN IF EXISTS practical_hours');
        DB::statement('ALTER TABLE curriculum.curriculum_subjects DROP COLUMN IF EXISTS theory_hours');
    }
};
