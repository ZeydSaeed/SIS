<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive: request_kind on admission.applications —
 * 1=vocational intake, 2=academic→vocational transfer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission.applications', function (Blueprint $table) {
            $table->smallInteger('request_kind')->default(1);
        });

        DB::statement(
            'ALTER TABLE admission.applications
             ADD CONSTRAINT applications_request_kind_check
             CHECK (request_kind IN (1, 2))'
        );

        // Existing converted rows without transfer grade stay vocational (default 1).
        // Rows that already captured an intended grade are treated as academic transfer.
        DB::statement(
            'UPDATE admission.applications
             SET request_kind = 2
             WHERE grade_level_id IS NOT NULL
                OR (intended_grade_name IS NOT NULL AND btrim(intended_grade_name) <> \'\')'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE admission.applications DROP CONSTRAINT IF EXISTS applications_request_kind_check');

        Schema::table('admission.applications', function (Blueprint $table) {
            $table->dropColumn('request_kind');
        });
    }
};
