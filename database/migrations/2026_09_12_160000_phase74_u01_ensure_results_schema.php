<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 7.4-U01 — Ensure results schema exists (SchemaHelper SSOT).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('CREATE SCHEMA IF NOT EXISTS results');
    }

    public function down(): void
    {
        // Do not DROP SCHEMA — may contain objects; human-approved cleanup only.
    }
};
