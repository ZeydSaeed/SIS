<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Branches / departments use three states: 1 نشط, 2 غير نشط, 3 مؤرشف («حذف» archives).
 * Legacy rows with status 0 (seeded "inactive") become 2. Data-only, no schema change.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['branches', 'departments'] as $table) {
            DB::table(SchemaHelper::qualified('organization', $table))
                ->where('status', 0)
                ->update(['status' => 2, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Irreversible mapping (0 and 2 both mean "inactive"); nothing to restore.
    }
};
