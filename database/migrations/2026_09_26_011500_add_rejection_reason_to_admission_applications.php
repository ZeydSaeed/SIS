<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dedicated rejection reason on admission applications (separate from general notes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission.applications', function (Blueprint $table): void {
            $table->text('rejection_reason')->nullable()->after('notes');
        });

        DB::statement(
            "UPDATE admission.applications
             SET rejection_reason = NULLIF(BTRIM(notes), '')
             WHERE status = 7
               AND rejection_reason IS NULL
               AND notes IS NOT NULL
               AND BTRIM(notes) <> ''"
        );
    }

    public function down(): void
    {
        Schema::table('admission.applications', function (Blueprint $table): void {
            $table->dropColumn('rejection_reason');
        });
    }
};
