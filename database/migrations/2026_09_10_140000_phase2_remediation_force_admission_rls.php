<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 2 remediation — FORCE RLS on admission so policies apply to table owners
 * (fail-closed isolation is otherwise bypassed by the owning role).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('ALTER TABLE admission.application_periods FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE admission.applications FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE admission.application_documents FORCE ROW LEVEL SECURITY');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('ALTER TABLE admission.application_documents NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE admission.applications NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE admission.application_periods NO FORCE ROW LEVEL SECURITY');
    }
};
