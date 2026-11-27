<?php

use App\Database\SafeDeleteTriggerBypass;
use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;

/**
 * «حذف آمن» batch 3 — structure and rooms. Branches, departments, rooms, directorates and schools have no
 * no-delete trigger (foreign keys alone refuse a used one); these three do.
 */
return new class extends Migration
{
    private const TABLES = [
        'organization.room_types',
        'enrollment.classes',
        'enrollment.sections',
    ];

    public function up(): void
    {
        if (SchemaHelper::isPostgreSql()) {
            SafeDeleteTriggerBypass::install(self::TABLES);
        }
    }

    public function down(): void
    {
        if (SchemaHelper::isPostgreSql()) {
            SafeDeleteTriggerBypass::uninstall(self::TABLES);
        }
    }
};
