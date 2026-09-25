<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store vocational branch label on admission applications (fixed catalog, independent of org.branches).
 * Impact: LOW — additive nullable column.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = SchemaHelper::qualified('admission', 'applications');

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->string('branch_name', 100)->nullable();
        });
    }

    public function down(): void
    {
        $table = SchemaHelper::qualified('admission', 'applications');

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropColumn('branch_name');
        });
    }
};
