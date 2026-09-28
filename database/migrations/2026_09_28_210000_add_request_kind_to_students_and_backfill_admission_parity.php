<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Persist admission channel on student + backfill convert gaps.
 * - request_kind: 1=academic→vocational transfer, 2=vocational intake (nullable if not from admission)
 * - Backfill parity columns + request_kind from linked admission.applications
 * - Copy application_documents → student_documents for converted students missing copies
 */
return new class extends Migration
{
    public function up(): void
    {
        $students = SchemaHelper::qualified('students', 'students');

        if (! Schema::hasColumn($students, 'request_kind')) {
            Schema::table($students, function (Blueprint $blueprint): void {
                $blueprint->smallInteger('request_kind')->nullable();
            });

            if (SchemaHelper::isPostgreSql()) {
                DB::statement("ALTER TABLE {$students} ADD CONSTRAINT students_request_kind_chk CHECK (request_kind IS NULL OR request_kind IN (1, 2))");
            }
        }

        $applications = SchemaHelper::qualified('admission', 'applications');
        $appDocs = SchemaHelper::qualified('admission', 'application_documents');
        $stuDocs = SchemaHelper::qualified('students', 'student_documents');

        // Backfill admission channel + parity fields onto converted students (only fill nulls).
        DB::statement("
            UPDATE {$students} AS s
            SET
                request_kind = COALESCE(s.request_kind, a.request_kind),
                father_occupation = COALESCE(s.father_occupation, a.father_occupation),
                mother_occupation = COALESCE(s.mother_occupation, a.mother_occupation),
                administrative_unit = COALESCE(s.administrative_unit, a.administrative_unit),
                graduation_year = COALESCE(s.graduation_year, a.graduation_year),
                previous_gpa = COALESCE(s.previous_gpa, a.previous_gpa),
                previous_study_track = COALESCE(
                    s.previous_study_track,
                    CASE WHEN a.previous_study_track IN (1, 2, 3, 4, 5) THEN a.previous_study_track ELSE NULL END
                ),
                mathematics_grade = COALESCE(s.mathematics_grade, a.mathematics_grade),
                physics_grade = COALESCE(s.physics_grade, a.physics_grade),
                previous_school_name = COALESCE(s.previous_school_name, a.previous_school_name),
                mobile = COALESCE(s.mobile, a.student_mobile),
                guardian_mobile = COALESCE(s.guardian_mobile, a.guardian_mobile),
                governorate = COALESCE(s.governorate, a.governorate),
                neighborhood = COALESCE(s.neighborhood, a.neighborhood),
                department_name = COALESCE(s.department_name, a.department_name),
                admitted_class_name = COALESCE(s.admitted_class_name, a.intended_grade_name),
                class_name = COALESCE(s.class_name, a.intended_grade_name),
                notes = COALESCE(s.notes, a.notes),
                updated_at = NOW()
            FROM {$applications} AS a
            WHERE a.student_id = s.id
        ");

        // Copy admission documents onto the student record when no active student doc of same type exists.
        DB::statement("
            INSERT INTO {$stuDocs} (
                school_id, student_id, document_type, storage_key, file_name,
                mime_type, file_size, file_hash, uploaded_by, status, created_at
            )
            SELECT
                p.school_id,
                a.student_id,
                d.document_type,
                d.storage_key,
                d.file_name,
                CASE
                    WHEN lower(d.file_name) LIKE '%.png' THEN 'image/png'
                    WHEN lower(d.file_name) LIKE '%.webp' THEN 'image/webp'
                    WHEN lower(d.file_name) LIKE '%.pdf' THEN 'application/pdf'
                    ELSE 'image/jpeg'
                END,
                0,
                d.file_hash,
                NULL,
                1,
                COALESCE(d.created_at, NOW())
            FROM {$appDocs} AS d
            INNER JOIN {$applications} AS a ON a.id = d.application_id
            INNER JOIN {$students} AS s ON s.id = a.student_id
            INNER JOIN ".SchemaHelper::qualified('admission', 'application_periods').' AS p ON p.id = a.application_period_id
            WHERE a.student_id IS NOT NULL
              AND NOT EXISTS (
                  SELECT 1
                  FROM '.$stuDocs.' AS existing
                  WHERE existing.student_id = a.student_id
                    AND existing.document_type = d.document_type
                    AND existing.status = 1
              )
        ');
    }

    public function down(): void
    {
        $students = SchemaHelper::qualified('students', 'students');

        if (SchemaHelper::isPostgreSql()) {
            DB::statement("ALTER TABLE {$students} DROP CONSTRAINT IF EXISTS students_request_kind_chk");
        }

        Schema::table($students, function (Blueprint $blueprint): void {
            $blueprint->dropColumn('request_kind');
        });
    }
};
