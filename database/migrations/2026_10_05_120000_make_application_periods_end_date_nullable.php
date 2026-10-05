<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An application period may be opened without an end date (open-ended: it stays
 * open until archived/deactivated). Additive: NOT NULL is relaxed only; the
 * existing CHECK (end_date >= start_date) passes for NULL and stays in place.
 * down() refuses while open-ended rows exist instead of inventing end dates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(SchemaHelper::qualified('admission', 'application_periods'), function (Blueprint $table): void {
            $table->timestamp('end_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        $table = SchemaHelper::qualified('admission', 'application_periods');

        if (DB::table($table)->whereNull('end_date')->exists()) {
            throw new RuntimeException('Cannot restore NOT NULL: open-ended application periods exist.');
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->timestamp('end_date')->nullable(false)->change();
        });
    }
};
