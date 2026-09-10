<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 2 — create admission schema (additive).
 * SchemaHelper::schemas() now includes admission for fresh installs;
 * this migration applies CREATE SCHEMA on already-migrated databases.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('CREATE SCHEMA IF NOT EXISTS admission');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        // Only drop if empty of Phase 2 tables (tables migration drops first on rollback).
        DB::statement('DROP SCHEMA IF EXISTS admission CASCADE');
    }
};
