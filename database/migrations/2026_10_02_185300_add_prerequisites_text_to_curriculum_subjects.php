<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Additive optional free-text prerequisites note on curriculum.subjects.
 * Distinct from curriculum.prerequisites (formal subject→subject edges).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('
            ALTER TABLE curriculum.subjects
            ADD COLUMN IF NOT EXISTS prerequisites_text VARCHAR(500) NULL
        ');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('
            ALTER TABLE curriculum.subjects
            DROP COLUMN IF EXISTS prerequisites_text
        ');
    }
};
