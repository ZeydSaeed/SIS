<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase TV-U06 — additive status on specialization_subjects for soft deactivate (no hard delete).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('
            ALTER TABLE vocational.specialization_subjects
            ADD COLUMN IF NOT EXISTS status SMALLINT NOT NULL DEFAULT 1
        ');
        DB::statement('
            ALTER TABLE vocational.specialization_subjects
            DROP CONSTRAINT IF EXISTS specialization_subjects_status_check
        ');
        DB::statement('
            ALTER TABLE vocational.specialization_subjects
            ADD CONSTRAINT specialization_subjects_status_check
            CHECK (status BETWEEN 1 AND 2)
        ');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('
            ALTER TABLE vocational.specialization_subjects
            DROP CONSTRAINT IF EXISTS specialization_subjects_status_check
        ');
        DB::statement('
            ALTER TABLE vocational.specialization_subjects
            DROP COLUMN IF EXISTS status
        ');
    }
};