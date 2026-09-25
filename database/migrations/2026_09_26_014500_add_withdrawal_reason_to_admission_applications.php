<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dedicated withdrawal reason on admission applications.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission.applications', function (Blueprint $table): void {
            $table->text('withdrawal_reason')->nullable()->after('rejection_reason');
        });

        DB::statement(
            "UPDATE admission.applications
             SET withdrawal_reason = NULLIF(BTRIM(notes), '')
             WHERE status = 8
               AND withdrawal_reason IS NULL
               AND notes IS NOT NULL
               AND BTRIM(notes) <> ''"
        );
    }

    public function down(): void
    {
        Schema::table('admission.applications', function (Blueprint $table): void {
            $table->dropColumn('withdrawal_reason');
        });
    }
};
