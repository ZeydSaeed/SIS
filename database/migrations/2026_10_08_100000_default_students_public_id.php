<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * students.students.public_id was created nullable with no default, so every student had NULL while the
 * blueprint (and StudentDetailDTO) promise a UUID. Backfill, then enforce what the blueprint says:
 * UNIQUE NOT NULL DEFAULT gen_random_uuid().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('UPDATE students.students SET public_id = gen_random_uuid() WHERE public_id IS NULL');
        DB::statement('ALTER TABLE students.students ALTER COLUMN public_id SET DEFAULT gen_random_uuid()');
        DB::statement('ALTER TABLE students.students ALTER COLUMN public_id SET NOT NULL');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('ALTER TABLE students.students ALTER COLUMN public_id DROP NOT NULL');
        DB::statement('ALTER TABLE students.students ALTER COLUMN public_id DROP DEFAULT');
    }
};
