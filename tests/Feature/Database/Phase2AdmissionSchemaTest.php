<?php

namespace Tests\Feature\Database;

use App\Database\SchemaHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class Phase2AdmissionSchemaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admission_tables_exist_with_expected_columns(): void
    {
        $periods = SchemaHelper::qualified('admission', 'application_periods');
        $applications = SchemaHelper::qualified('admission', 'applications');
        $documents = SchemaHelper::qualified('admission', 'application_documents');

        $this->assertTrue(Schema::hasTable($periods));
        $this->assertTrue(Schema::hasTable($applications));
        $this->assertTrue(Schema::hasTable($documents));

        $this->assertTrue(Schema::hasColumns($periods, [
            'id', 'academic_year_id', 'school_id', 'name', 'start_date', 'end_date',
            'max_applications', 'status', 'created_at',
        ]));
        $this->assertTrue(Schema::hasColumns($applications, [
            'id', 'application_period_id', 'application_number', 'first_name', 'last_name',
            'national_id', 'birth_date', 'gender', 'grade_level_id', 'specialization_id',
            'status', 'submitted_at', 'reviewed_by', 'reviewed_at', 'notes', 'student_id',
            'created_at', 'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns($documents, [
            'id', 'application_id', 'document_type', 'storage_key', 'file_name', 'file_hash', 'created_at',
        ]));
    }

    #[Test]
    public function admission_schema_is_registered_in_schema_helper(): void
    {
        $this->assertContains('admission', SchemaHelper::schemas());
    }

    #[Test]
    public function applications_do_not_create_duplicate_student_master_table(): void
    {
        $this->assertFalse(Schema::hasTable(SchemaHelper::qualified('admission', 'students')));
        $this->assertFalse(Schema::hasTable(SchemaHelper::qualified('admission', 'persons')));
        $this->assertTrue(Schema::hasTable(SchemaHelper::qualified('students', 'students')));
    }

    #[Test]
    public function postgresql_check_constraints_and_rls_exist(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            $this->markTestSkipped('PostgreSQL required for CHECK/RLS catalog assertions.');
        }

        $checks = collect(DB::select("
            SELECT con.conname
            FROM pg_constraint con
            JOIN pg_class rel ON rel.oid = con.conrelid
            JOIN pg_namespace nsp ON nsp.oid = rel.relnamespace
            WHERE nsp.nspname = 'admission' AND con.contype = 'c'
        "))->pluck('conname');

        $this->assertTrue($checks->contains('application_periods_date_range_check'));
        $this->assertTrue($checks->contains('applications_status_check'));
        $this->assertTrue($checks->contains('applications_gender_check'));

        $rls = DB::selectOne("
            SELECT c.relrowsecurity
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'admission' AND c.relname = 'applications'
        ");
        $this->assertTrue((bool) $rls->relrowsecurity);

        $policies = collect(DB::select("
            SELECT policyname FROM pg_policies WHERE schemaname = 'admission'
        "))->pluck('policyname');

        $this->assertTrue($policies->contains('admission_periods_school_isolation'));
        $this->assertTrue($policies->contains('admission_applications_school_isolation'));
        $this->assertTrue($policies->contains('admission_documents_school_isolation'));
    }
}
