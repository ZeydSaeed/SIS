<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «نوع المادة» gains «نظري» (theory) = 4, next to 1 أساسي · 2 اختصاص · 3 عملي.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('ALTER TABLE curriculum.subjects DROP CONSTRAINT IF EXISTS curriculum_subjects_type_check');
        DB::statement('ALTER TABLE curriculum.subjects ADD CONSTRAINT curriculum_subjects_type_check CHECK (subject_type IN (1, 2, 3, 4))');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        // Theory subjects fall back to «أساسي» (1), the meaning «نظري» had before this type existed.
        DB::statement('UPDATE curriculum.subjects SET subject_type = 1 WHERE subject_type = 4');
        DB::statement('ALTER TABLE curriculum.subjects DROP CONSTRAINT IF EXISTS curriculum_subjects_type_check');
        DB::statement('ALTER TABLE curriculum.subjects ADD CONSTRAINT curriculum_subjects_type_check CHECK (subject_type IN (1, 2, 3))');
    }
};
