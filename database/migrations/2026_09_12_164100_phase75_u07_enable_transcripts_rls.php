<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('ALTER TABLE results.transcripts ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE results.transcripts FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY transcripts_school_isolation ON results.transcripts
            USING (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
            )
            WITH CHECK (
                NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
            )
        ");
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS transcripts_school_isolation ON results.transcripts');
        DB::statement('ALTER TABLE results.transcripts NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE results.transcripts DISABLE ROW LEVEL SECURITY');
    }
};
