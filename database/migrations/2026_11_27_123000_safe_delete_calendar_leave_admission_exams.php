<?php

use App\Database\SafeDeleteTriggerBypass;
use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;

/**
 * «حذف آمن» batch 4 — holidays, leave types (+ policy versions), admission periods, exams (+ sessions).
 * Only the leave tables carry a no-delete trigger; the others rely on foreign keys alone.
 */
return new class extends Migration
{
    private const TABLES = [
        'leave.types',
        'leave.policies',
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
