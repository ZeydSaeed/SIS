<?php

use App\Database\SafeDeleteTriggerBypass;
use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;

/** «حذف آمن» batch 2 — subjects, curricula, curriculum subject lines and prerequisites. */
return new class extends Migration
{
    private const TABLES = [
        'curriculum.subjects',
        'curriculum.curricula',
        'curriculum.curriculum_subjects',
        'curriculum.prerequisites',
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
