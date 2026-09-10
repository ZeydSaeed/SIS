<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3C.12 M01 — Ensure graduation schema exists (SchemaHelper SSOT).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('CREATE SCHEMA IF NOT EXISTS graduation');
    }

    public function down(): void
    {
        // Do not DROP SCHEMA — may contain objects; human-approved cleanup only.
    }
};
