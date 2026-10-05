<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Transfers page: an UPDATE with a WHERE clause re-checks the new row against SELECT
 * policies, so the moved application must be readable in the target school while the
 * transaction-local app.transfer_target_school_id is set (only by the transfer handler,
 * after authorization). Without the setting this policy admits nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        $apps = SchemaHelper::qualified('admission', 'applications');
        $target = "NULLIF(current_setting('app.transfer_target_school_id', true), '')";

        DB::statement("DROP POLICY IF EXISTS admission_applications_transfer_target_read ON {$apps}");
        DB::statement("
            CREATE POLICY admission_applications_transfer_target_read ON {$apps}
            FOR SELECT
            USING ({$target} IS NOT NULL AND school_id = {$target}::BIGINT)
        ");
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS admission_applications_transfer_target_read ON '.SchemaHelper::qualified('admission', 'applications'));
    }
};
