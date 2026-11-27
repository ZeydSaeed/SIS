<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «نوع المادة» takes several values (owner, 2026-11-27): a subject may be عملي + نظري, or أساسي + عملي …
 *
 * curriculum.subject_types (new, normalized — one row per type of a subject; global like curriculum.subjects, no
 * school_id / RLS): PK (subject_id, subject_type), FK subject restrict, CHECK type 1–4.
 * curriculum.subjects.subject_type stays: the subject's primary type (the first chosen, in the order أساسي، اختصاص،
 * عملي، نظري) — attendance and the timetable keep reading it. Trigger subjects_primary_type_in_set: every insert /
 * type change of a subject puts its primary type into the set, whoever writes it (seeders, imports, API).
 * Backfill: each existing subject gets its current type.
 */
return new class extends Migration
{
    public function up(): void
    {
        $types = SchemaHelper::qualified('curriculum', 'subject_types');
        if (! Schema::hasTable($types)) {
            Schema::create($types, function (Blueprint $table): void {
                $table->foreignId('subject_id')->constrained(SchemaHelper::qualified('curriculum', 'subjects'))->restrictOnDelete();
                $table->smallInteger('subject_type');
                $table->timestampTz('created_at')->useCurrent();
                $table->primary(['subject_id', 'subject_type']);
            });
        }

        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement("ALTER TABLE {$types} DROP CONSTRAINT IF EXISTS subject_types_type_check");
        DB::statement("ALTER TABLE {$types} ADD CONSTRAINT subject_types_type_check CHECK (subject_type BETWEEN 1 AND 4)");
        DB::statement("
            INSERT INTO {$types} (subject_id, subject_type)
            SELECT id, subject_type FROM curriculum.subjects
            ON CONFLICT DO NOTHING
        ");

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION curriculum.subject_primary_type_in_set()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                INSERT INTO curriculum.subject_types (subject_id, subject_type)
                VALUES (NEW.id, NEW.subject_type)
                ON CONFLICT DO NOTHING;
                RETURN NEW;
            END; $$
        SQL);
        DB::statement('DROP TRIGGER IF EXISTS subjects_primary_type_in_set ON curriculum.subjects');
        DB::statement('
            CREATE TRIGGER subjects_primary_type_in_set
            AFTER INSERT OR UPDATE OF subject_type ON curriculum.subjects
            FOR EACH ROW EXECUTE FUNCTION curriculum.subject_primary_type_in_set()
        ');
    }

    public function down(): void
    {
        if (SchemaHelper::isPostgreSql()) {
            DB::statement('DROP TRIGGER IF EXISTS subjects_primary_type_in_set ON curriculum.subjects');
            DB::statement('DROP FUNCTION IF EXISTS curriculum.subject_primary_type_in_set()');
        }
        Schema::dropIfExists(SchemaHelper::qualified('curriculum', 'subject_types'));
    }
};
