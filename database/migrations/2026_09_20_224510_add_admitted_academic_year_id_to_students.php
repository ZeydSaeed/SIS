<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive nullable FK students.admitted_academic_year_id → academic.academic_years.
 * Captures the admission period year onto the student identity after conversion so
 * year-scoped lists and later enrollment stay linked to that year.
 * Index justified: student list WHERE admitted_academic_year_id = :year.
 * Impact: MEDIUM (nullable FK + btree). See database-blueprint.md / indexing-matrix.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = SchemaHelper::qualified('students', 'students');

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->foreignId('admitted_academic_year_id')
                ->nullable()
                ->constrained(SchemaHelper::qualified('academic', 'academic_years'))
                ->restrictOnDelete();
        });

        $this->backfillFromConvertedApplications();
    }

    public function down(): void
    {
        $table = SchemaHelper::qualified('students', 'students');

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropConstrainedForeignId('admitted_academic_year_id');
        });
    }

    private function backfillFromConvertedApplications(): void
    {
        $studentsTable = SchemaHelper::qualified('students', 'students');
        $applicationsTable = SchemaHelper::qualified('admission', 'applications');
        $periodsTable = SchemaHelper::qualified('admission', 'application_periods');

        $rows = DB::table($applicationsTable.' as apps')
            ->join($periodsTable.' as periods', 'periods.id', '=', 'apps.application_period_id')
            ->whereNotNull('apps.student_id')
            ->orderBy('apps.id')
            ->get([
                'apps.student_id',
                'periods.academic_year_id',
            ]);

        $latestByStudent = [];
        foreach ($rows as $row) {
            $latestByStudent[(int) $row->student_id] = (int) $row->academic_year_id;
        }

        foreach ($latestByStudent as $studentId => $yearId) {
            DB::table($studentsTable)
                ->where('id', $studentId)
                ->whereNull('admitted_academic_year_id')
                ->update(['admitted_academic_year_id' => $yearId]);
        }
    }
};
