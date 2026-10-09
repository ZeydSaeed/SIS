<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A vocational specialization mirrors its department 1:1, yet applications and curricula that name a department
 * carried no specialization (100% empty on demo data). The department is the single source; the specialization
 * follows it — now by the repositories on every write, and for existing rows here. Data only, no schema change;
 * rows without a mirrored specialization are left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('
            UPDATE admission.applications a
            SET specialization_id = s.id, specialization_name = s.name
            FROM vocational.specializations s
            WHERE a.department_id IS NOT NULL AND s.department_id = a.department_id AND s.school_id = a.school_id
              AND s.status = 1 AND (a.specialization_id IS DISTINCT FROM s.id)
        ');
        DB::statement('
            UPDATE curriculum.curricula c
            SET specialization_id = s.id
            FROM vocational.specializations s
            WHERE c.department_id IS NOT NULL AND s.department_id = c.department_id AND s.school_id = c.school_id
              AND s.status = 1 AND c.specialization_id IS NULL
        ');
    }

    public function down(): void
    {
        // Derived data: nothing to undo.
    }
};
