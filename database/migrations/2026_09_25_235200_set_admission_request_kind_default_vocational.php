<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Default request_kind = 2 (vocational intake).
 * Semantics: 1 = academic→vocational transfer, 2 = vocational school intake.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'ALTER TABLE admission.applications
             ALTER COLUMN request_kind SET DEFAULT 2'
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE admission.applications
             ALTER COLUMN request_kind SET DEFAULT 1'
        );
    }
};
