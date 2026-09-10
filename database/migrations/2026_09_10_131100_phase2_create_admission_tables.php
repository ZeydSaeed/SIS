<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — admission.application_periods, applications, application_documents.
 * Blueprint SSOT (3 tables). Additive student_id on applications for conversion link only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(SchemaHelper::qualified('admission', 'application_periods'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')
                ->constrained(SchemaHelper::qualified('academic', 'academic_years'))
                ->restrictOnDelete();
            $table->foreignId('school_id')
                ->constrained(SchemaHelper::qualified('organization', 'schools'))
                ->restrictOnDelete();
            $table->string('name');
            $table->timestamp('start_date');
            $table->timestamp('end_date');
            $table->unsignedInteger('max_applications')->nullable();
            $table->smallInteger('status')->default(1);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['academic_year_id', 'school_id']);
        });

        Schema::create(SchemaHelper::qualified('admission', 'applications'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_period_id')
                ->constrained(SchemaHelper::qualified('admission', 'application_periods'))
                ->restrictOnDelete();
            $table->string('application_number', 50)->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('national_id', 20)->nullable();
            $table->date('birth_date');
            $table->smallInteger('gender');
            $table->unsignedSmallInteger('grade_level_id');
            $table->foreign('grade_level_id')
                ->references('id')
                ->on(SchemaHelper::qualified('academic', 'grade_levels'))
                ->restrictOnDelete();
            $table->foreignId('specialization_id')
                ->nullable()
                ->constrained(SchemaHelper::qualified('vocational', 'specializations'))
                ->restrictOnDelete();
            $table->smallInteger('status')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('student_id')
                ->nullable()
                ->constrained(SchemaHelper::qualified('students', 'students'))
                ->restrictOnDelete();
            $table->timestamps();

            $table->index('application_period_id');
            $table->index('status');
        });

        Schema::create(SchemaHelper::qualified('admission', 'application_documents'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')
                ->constrained(SchemaHelper::qualified('admission', 'applications'))
                ->restrictOnDelete();
            $table->smallInteger('document_type');
            $table->string('storage_key', 500);
            $table->string('file_name');
            $table->string('file_hash', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->index('application_id');
        });

        if (SchemaHelper::isPostgreSql()) {
            $this->addPostgreSqlChecksAndPartialIndex();
        }
    }

    private function addPostgreSqlChecksAndPartialIndex(): void
    {
        DB::statement('
            ALTER TABLE admission.application_periods
            ADD CONSTRAINT application_periods_date_range_check
            CHECK (end_date >= start_date)
        ');
        DB::statement('
            ALTER TABLE admission.application_periods
            ADD CONSTRAINT application_periods_max_applications_check
            CHECK (max_applications IS NULL OR max_applications > 0)
        ');
        DB::statement('
            ALTER TABLE admission.application_periods
            ADD CONSTRAINT application_periods_status_check
            CHECK (status IN (0, 1, 2))
        ');
        DB::statement('
            ALTER TABLE admission.applications
            ADD CONSTRAINT applications_status_check
            CHECK (status BETWEEN 1 AND 9)
        ');
        DB::statement('
            ALTER TABLE admission.applications
            ADD CONSTRAINT applications_gender_check
            CHECK (gender IN (1, 2))
        ');
        DB::statement('
            ALTER TABLE admission.application_documents
            ADD CONSTRAINT application_documents_document_type_check
            CHECK (document_type > 0)
        ');
        DB::statement('
            CREATE INDEX applications_student_id_converted_idx
            ON admission.applications (student_id)
            WHERE student_id IS NOT NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists(SchemaHelper::qualified('admission', 'application_documents'));
        Schema::dropIfExists(SchemaHelper::qualified('admission', 'applications'));
        Schema::dropIfExists(SchemaHelper::qualified('admission', 'application_periods'));
    }
};
