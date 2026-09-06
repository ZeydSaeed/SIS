<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_stat_statements');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP EXTENSION IF EXISTS pg_stat_statements');
    }
};
