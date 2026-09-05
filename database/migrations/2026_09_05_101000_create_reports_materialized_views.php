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

        DB::statement('
            CREATE MATERIALIZED VIEW reports.mv_school_student_statistics AS
            SELECT
                e.school_id,
                e.academic_year_id,
                COUNT(DISTINCT e.student_id)::INTEGER AS total_students,
                COUNT(DISTINCT e.student_id) FILTER (WHERE e.status = 1)::INTEGER AS active_students,
                NOW() AS refreshed_at
            FROM enrollment.enrollments e
            GROUP BY e.school_id, e.academic_year_id
            WITH NO DATA
        ');

        DB::statement('
            CREATE UNIQUE INDEX mv_school_student_statistics_pk
            ON reports.mv_school_student_statistics (school_id, academic_year_id)
        ');

        DB::statement('
            CREATE MATERIALIZED VIEW reports.mv_daily_attendance AS
            SELECT
                s.school_id,
                s.section_id,
                s.academic_year_id,
                s.attendance_date,
                s.total_students,
                s.present_count,
                s.absent_count,
                s.late_count,
                CASE
                    WHEN s.total_students > 0
                    THEN ROUND((s.present_count::NUMERIC / s.total_students) * 100, 2)
                    ELSE 0
                END AS attendance_percentage,
                NOW() AS refreshed_at
            FROM attendance.daily_section_summary s
            WITH NO DATA
        ');

        DB::statement('
            CREATE UNIQUE INDEX mv_daily_attendance_pk
            ON reports.mv_daily_attendance (section_id, attendance_date)
        ');

        DB::statement('
            CREATE INDEX mv_daily_attendance_school_date_idx
            ON reports.mv_daily_attendance (school_id, attendance_date)
        ');

        DB::statement('
            CREATE MATERIALIZED VIEW reports.mv_directorate_school_comparison AS
            SELECT
                sch.directorate_id,
                s.school_id,
                s.academic_year_id,
                SUM(s.total_students)::INTEGER AS total_students,
                SUM(s.present_count)::INTEGER AS present_count,
                SUM(s.absent_count)::INTEGER AS absent_count,
                CASE
                    WHEN SUM(s.total_students) > 0
                    THEN ROUND((SUM(s.present_count)::NUMERIC / SUM(s.total_students)) * 100, 2)
                    ELSE 0
                END AS attendance_percentage,
                NOW() AS refreshed_at
            FROM attendance.daily_section_summary s
            JOIN organization.schools sch ON sch.id = s.school_id
            GROUP BY sch.directorate_id, s.school_id, s.academic_year_id
            WITH NO DATA
        ');

        DB::statement('
            CREATE UNIQUE INDEX mv_directorate_school_comparison_pk
            ON reports.mv_directorate_school_comparison (school_id, academic_year_id, directorate_id)
        ');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP MATERIALIZED VIEW IF EXISTS reports.mv_directorate_school_comparison');
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS reports.mv_daily_attendance');
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS reports.mv_school_student_statistics');
    }
};
