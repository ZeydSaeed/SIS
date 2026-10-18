<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «الاجازات» (2026-10-18): a holiday is never deleted — it is taken out of the calendar (status 2) and can be put
 * back (status 1). Additive only: existing rows stay in force (status 1), nothing is dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = SchemaHelper::qualified('academic', 'holidays');

        Schema::table($table, function (Blueprint $table): void {
            $table->smallInteger('status')->default(1);
            $table->timestamp('updated_at')->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT holidays_status_check CHECK (status IN (1, 2))");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT holidays_dates_check CHECK (end_date >= start_date)");
        }
    }

    public function down(): void
    {
        $table = SchemaHelper::qualified('academic', 'holidays');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS holidays_dates_check");
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS holidays_status_check");
        }

        Schema::table($table, function (Blueprint $table): void {
            $table->dropColumn(['status', 'updated_at']);
        });
    }
};
