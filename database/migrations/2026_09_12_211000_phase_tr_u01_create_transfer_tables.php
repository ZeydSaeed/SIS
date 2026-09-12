<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase TR-U01 — Physicalize transfers.transfer_requests + transfer_records.
 *
 * HD-TR-002/005: dual-school columns on records for FORCE RLS.
 * CompleteTransfer writers deferred.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('CREATE SCHEMA IF NOT EXISTS transfers');

        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS enrollments_id_school_id_unique
            ON enrollment.enrollments (id, school_id)
        ');
        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS enrollments_id_academic_year_id_unique
            ON enrollment.enrollments (id, academic_year_id)
        ');

        DB::statement("
            CREATE TABLE transfers.transfer_requests (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                student_id BIGINT NOT NULL,
                from_school_id BIGINT NOT NULL,
                to_school_id BIGINT NOT NULL,
                from_enrollment_id BIGINT NOT NULL,
                academic_year_id BIGINT NOT NULL,
                reason TEXT,
                status SMALLINT NOT NULL DEFAULT 1,
                requested_by BIGINT,
                requested_at TIMESTAMPTZ NOT NULL,
                approved_by BIGINT,
                approved_at TIMESTAMPTZ,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT transfer_requests_student_fk
                    FOREIGN KEY (student_id) REFERENCES students.students(id) ON DELETE RESTRICT,
                CONSTRAINT transfer_requests_from_school_fk
                    FOREIGN KEY (from_school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT transfer_requests_to_school_fk
                    FOREIGN KEY (to_school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT transfer_requests_academic_year_fk
                    FOREIGN KEY (academic_year_id) REFERENCES academic.academic_years(id) ON DELETE RESTRICT,
                CONSTRAINT transfer_requests_enrollment_school_fk
                    FOREIGN KEY (from_enrollment_id, from_school_id)
                    REFERENCES enrollment.enrollments(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT transfer_requests_enrollment_year_fk
                    FOREIGN KEY (from_enrollment_id, academic_year_id)
                    REFERENCES enrollment.enrollments(id, academic_year_id) ON DELETE RESTRICT,
                CONSTRAINT transfer_requests_requested_by_fk
                    FOREIGN KEY (requested_by) REFERENCES public.users(id) ON DELETE SET NULL,
                CONSTRAINT transfer_requests_approved_by_fk
                    FOREIGN KEY (approved_by) REFERENCES public.users(id) ON DELETE SET NULL,
                CONSTRAINT transfer_requests_schools_distinct_check
                    CHECK (from_school_id <> to_school_id),
                CONSTRAINT transfer_requests_status_check
                    CHECK (status BETWEEN 1 AND 5)
            )
        ");

        DB::statement('
            CREATE INDEX transfer_requests_student_idx
            ON transfers.transfer_requests (student_id)
        ');
        DB::statement('
            CREATE INDEX transfer_requests_status_idx
            ON transfers.transfer_requests (status)
        ');
        DB::statement('
            CREATE INDEX transfer_requests_year_idx
            ON transfers.transfer_requests (academic_year_id)
        ');
        DB::statement('
            CREATE INDEX transfer_requests_from_school_idx
            ON transfers.transfer_requests (from_school_id, status)
        ');
        DB::statement('
            CREATE INDEX transfer_requests_to_school_idx
            ON transfers.transfer_requests (to_school_id, status)
        ');
        DB::statement('
            CREATE UNIQUE INDEX transfer_requests_pending_enrollment_unique
            ON transfers.transfer_requests (from_enrollment_id)
            WHERE status = 1
        ');

        DB::statement("
            CREATE TABLE transfers.transfer_records (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                transfer_request_id BIGINT NOT NULL,
                student_id BIGINT NOT NULL,
                from_school_id BIGINT NOT NULL,
                to_school_id BIGINT NOT NULL,
                from_enrollment_id BIGINT NOT NULL,
                to_enrollment_id BIGINT NOT NULL,
                effective_date DATE NOT NULL,
                completed_at TIMESTAMPTZ NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT transfer_records_request_fk
                    FOREIGN KEY (transfer_request_id) REFERENCES transfers.transfer_requests(id) ON DELETE RESTRICT,
                CONSTRAINT transfer_records_student_fk
                    FOREIGN KEY (student_id) REFERENCES students.students(id) ON DELETE RESTRICT,
                CONSTRAINT transfer_records_from_school_fk
                    FOREIGN KEY (from_school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT transfer_records_to_school_fk
                    FOREIGN KEY (to_school_id) REFERENCES organization.schools(id) ON DELETE RESTRICT,
                CONSTRAINT transfer_records_from_enrollment_fk
                    FOREIGN KEY (from_enrollment_id, from_school_id)
                    REFERENCES enrollment.enrollments(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT transfer_records_to_enrollment_fk
                    FOREIGN KEY (to_enrollment_id, to_school_id)
                    REFERENCES enrollment.enrollments(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT transfer_records_schools_distinct_check
                    CHECK (from_school_id <> to_school_id),
                CONSTRAINT transfer_records_request_unique
                    UNIQUE (transfer_request_id)
            )
        ");

        DB::statement('
            CREATE INDEX transfer_records_student_idx
            ON transfers.transfer_records (student_id)
        ');
        DB::statement('
            CREATE INDEX transfer_records_request_idx
            ON transfers.transfer_records (transfer_request_id)
        ');
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('DROP TABLE IF EXISTS transfers.transfer_records');
        DB::statement('DROP TABLE IF EXISTS transfers.transfer_requests');
    }
};
