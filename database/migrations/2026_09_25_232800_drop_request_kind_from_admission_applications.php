<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revert request_kind — feature rolled back two UI steps.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE admission.applications DROP CONSTRAINT IF EXISTS applications_request_kind_check');

        Schema::table('admission.applications', function (Blueprint $table) {
            $table->dropColumn('request_kind');
        });
    }

    public function down(): void
    {
        Schema::table('admission.applications', function (Blueprint $table) {
            $table->smallInteger('request_kind')->default(1);
        });

        DB::statement(
            'ALTER TABLE admission.applications
             ADD CONSTRAINT applications_request_kind_check
             CHECK (request_kind IN (1, 2))'
        );
    }
};
