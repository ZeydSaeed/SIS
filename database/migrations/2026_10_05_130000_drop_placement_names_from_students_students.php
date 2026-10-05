<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * User-approved (2026-10-05): the student keeps placement as ids only.
 * department_name / admitted_class_name duplicated department_id / grade_level_id
 * and drifted from them; the labels are now read from organization.departments /
 * academic.grade_levels.
 *
 * Fail-safe: refuses to drop while any student has a name without its id
 * (run `php artisan sis:backfill-student-placement-ids` first) — no label is lost.
 * down() re-adds the columns and refills them from the ids.
 */
return new class extends Migration
{
    public function up(): void
    {
        $students = SchemaHelper::qualified('students', 'students');
        if (! Schema::hasColumn($students, 'department_name') && ! Schema::hasColumn($students, 'admitted_class_name')) {
            return;
        }

        $unresolved = DB::table($students)
            ->where(fn ($query) => $query
                ->where(fn ($q) => $q->whereRaw("nullif(trim(department_name), '') is not null")->whereNull('department_id'))
                ->orWhere(fn ($q) => $q->whereRaw("nullif(trim(admitted_class_name), '') is not null")->whereNull('grade_level_id')))
            ->count();
        if ($unresolved > 0) {
            throw new RuntimeException(
                "{$unresolved} student(s) have a department / class name without its id. "
                .'Run `php artisan sis:backfill-student-placement-ids` and fix the remaining names first.'
            );
        }

        Schema::table($students, function (Blueprint $table) use ($students): void {
            $drop = array_values(array_filter(
                ['department_name', 'admitted_class_name'],
                static fn (string $column): bool => Schema::hasColumn($students, $column),
            ));
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }

    public function down(): void
    {
        $students = SchemaHelper::qualified('students', 'students');

        Schema::table($students, function (Blueprint $table) use ($students): void {
            if (! Schema::hasColumn($students, 'admitted_class_name')) {
                $table->string('admitted_class_name', 100)->nullable();
            }
            if (! Schema::hasColumn($students, 'department_name')) {
                $table->string('department_name', 100)->nullable();
            }
        });

        if (SchemaHelper::isPostgreSql()) {
            $departments = SchemaHelper::qualified('organization', 'departments');
            $gradeLevels = SchemaHelper::qualified('academic', 'grade_levels');
            DB::statement("UPDATE {$students} AS s SET department_name = d.name FROM {$departments} AS d WHERE d.id = s.department_id");
            DB::statement("UPDATE {$students} AS s SET admitted_class_name = g.name FROM {$gradeLevels} AS g WHERE g.id = s.grade_level_id");
        }
    }
};
