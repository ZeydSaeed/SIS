<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Admission already accepts previous_study_track = 6 (متوسطة — the intermediate-school graduates, the commonest
 * applicants) but students.students still allowed 1–5, so converting such an application to a student failed on the
 * CHECK. Widen the student constraint to the same set. Impact: LOW — widens a CHECK only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('ALTER TABLE students.students DROP CONSTRAINT IF EXISTS students_previous_study_track_chk');
        DB::statement('ALTER TABLE students.students ADD CONSTRAINT students_previous_study_track_chk CHECK (previous_study_track IS NULL OR previous_study_track IN (1, 2, 3, 4, 5, 6))');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('UPDATE students.students SET previous_study_track = NULL WHERE previous_study_track = 6');
        DB::statement('ALTER TABLE students.students DROP CONSTRAINT IF EXISTS students_previous_study_track_chk');
        DB::statement('ALTER TABLE students.students ADD CONSTRAINT students_previous_study_track_chk CHECK (previous_study_track IS NULL OR previous_study_track IN (1, 2, 3, 4, 5))');
    }
};
