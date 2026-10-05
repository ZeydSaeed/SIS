<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «الفروع والاختصاصات» page: a branch (الفرع) and a department (الاختصاص) carry an
 * optional description (الوصف). Additive only; deleting a branch / department on that
 * page deactivates it (status 2) — rows are referenced by applications, students,
 * enrollments and curricula and are never hard-deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['branches', 'departments'] as $name) {
            $table = SchemaHelper::qualified('organization', $name);
            if (! Schema::hasColumn($table, 'description')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->text('description')->nullable()->after('name');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['branches', 'departments'] as $name) {
            $table = SchemaHelper::qualified('organization', $name);
            if (Schema::hasColumn($table, 'description')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->dropColumn('description');
                });
            }
        }
    }
};
