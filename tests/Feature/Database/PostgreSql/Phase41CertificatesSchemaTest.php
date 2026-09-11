<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Database\PostgreSqlRlsActor;
use Tests\Support\Database\SeedsGraduationConcurrencyGraph;

/**
 * Phase 4.1 — Certificates DDL catalog + integrity + RLS on disposable sis_test.
 */
final class Phase41CertificatesSchemaTest extends PostgreSqlIntegrationTestCase
{
    use SeedsGraduationConcurrencyGraph;

    /** @var list<string> */
    private array $tables = [
        'certificate_templates',
        'certificate_template_versions',
        'certificates',
        'certificate_issuances',
        'certificate_artifacts',
        'certificate_generation_jobs',
    ];

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }

    /**
     * @return array{
     *     g:array,
     *     template_id:int,
     *     template_version_id:int,
     *     award_id:int,
     *     award_version_id:int,
     *     approval_id:int,
     *     outcome_version_id:int
     * }
     */
    private function seedAwardAndTemplate(): array
    {
        $g = $this->seedGraduationConcurrencyGraph();
        $this->bindSchool($g['school_a']);

        $outcomeId = (int) DB::table('graduation.completion_outcomes')->insertGetId([
            'school_id' => $g['school_a'],
            'enrollment_id' => $g['enrollment_a'],
            'student_id' => $g['student_a'],
            'academic_year_id' => $g['year_id'],
            'created_at' => now(),
        ]);
        $outcomeVersionId = (int) DB::table('graduation.completion_outcome_versions')->insertGetId([
            'school_id' => $g['school_a'],
            'completion_outcome_id' => $outcomeId,
            'version_no' => 1,
            'lifecycle_status' => 1,
            'evaluation_status' => 2,
            'eligibility_status' => 2,
            'eligibility_policy_version_id' => $g['policy_version_id'],
            'calculation_version' => 'calc-cert',
            'evaluated_at' => now(),
            'is_current_official' => true,
            'created_at' => now(),
        ]);
        $approvalId = (int) DB::table('graduation.graduation_approvals')->insertGetId([
            'school_id' => $g['school_a'],
            'enrollment_id' => $g['enrollment_a'],
            'completion_outcome_version_id' => $outcomeVersionId,
            'attempt_no' => 1,
            'decision_status' => 2,
            'requested_at' => now(),
            'requested_by' => 7,
            'decided_at' => now(),
            'decided_by' => 8,
            'correlation_id' => 'corr-cert-ap',
            'created_at' => now(),
        ]);
        $awardId = (int) DB::table('graduation.graduation_awards')->insertGetId([
            'school_id' => $g['school_a'],
            'enrollment_id' => $g['enrollment_a'],
            'student_id' => $g['student_a'],
            'academic_year_id' => $g['year_id'],
            'created_by' => 8,
            'created_at' => now(),
        ]);
        $awardVersionId = (int) DB::table('graduation.graduation_award_versions')->insertGetId([
            'school_id' => $g['school_a'],
            'graduation_award_id' => $awardId,
            'version_no' => 1,
            'graduation_approval_id' => $approvalId,
            'completion_outcome_version_id' => $outcomeVersionId,
            'lifecycle_status' => 1,
            'is_current_issued' => true,
            'awarded_at' => now(),
            'issued_by' => 8,
            'correlation_id' => 'corr-cert-aw',
            'created_at' => now(),
        ]);
        DB::table('graduation.graduation_awards')->where('id', $awardId)->update([
            'current_issued_version_id' => $awardVersionId,
        ]);

        $templateId = (int) DB::table('certificates.certificate_templates')->insertGetId([
            'school_id' => $g['school_a'],
            'certificate_type' => 1,
            'name' => 'Graduation Template',
            'status' => 2,
            'created_at' => now(),
        ]);
        $templateVersionId = (int) DB::table('certificates.certificate_template_versions')->insertGetId([
            'school_id' => $g['school_a'],
            'template_id' => $templateId,
            'version_no' => 1,
            'locale' => 'ar',
            'content_hash' => str_repeat('a', 64),
            'template_storage_key' => 'templates/grad-v1',
            'created_at' => now(),
        ]);

        return [
            'g' => $g,
            'template_id' => $templateId,
            'template_version_id' => $templateVersionId,
            'award_id' => $awardId,
            'award_version_id' => $awardVersionId,
            'approval_id' => $approvalId,
            'outcome_version_id' => $outcomeVersionId,
        ];
    }

    #[Test]
    public function certificates_schema_tables_exist(): void
    {
        $this->assertContains('certificates', SchemaHelper::schemas());

        foreach ($this->tables as $table) {
            $this->assertTrue(
                Schema::hasTable(SchemaHelper::qualified('certificates', $table)),
                "Missing certificates.{$table}"
            );
        }

        $this->assertFalse(Schema::hasTable(SchemaHelper::qualified('certificates', 'issued_certificates')));
        $this->assertFalse(Schema::hasTable(SchemaHelper::qualified('certificates', 'templates')));
    }

    #[Test]
    public function rls_force_and_school_isolation_policies_exist(): void
    {
        foreach ($this->tables as $table) {
            $row = DB::selectOne('
                SELECT c.relrowsecurity AS rls, c.relforcerowsecurity AS force_rls
                FROM pg_class c
                JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = ? AND c.relname = ?
            ', ['certificates', $table]);

            $this->assertNotNull($row, $table);
            $this->assertTrue((bool) $row->rls, "RLS missing on {$table}");
            $this->assertTrue((bool) $row->force_rls, "FORCE RLS missing on {$table}");

            $policy = DB::selectOne('
                SELECT 1 AS ok
                FROM pg_policies
                WHERE schemaname = ? AND tablename = ? AND policyname = ?
            ', ['certificates', $table, "{$table}_school_isolation"]);

            $this->assertNotNull($policy, "Policy missing on {$table}");
        }
    }

    #[Test]
    public function reject_delete_triggers_exist_on_all_certificate_tables(): void
    {
        foreach ($this->tables as $table) {
            $exists = DB::selectOne('
                SELECT 1 AS ok
                FROM pg_trigger t
                JOIN pg_class c ON c.oid = t.tgrelid
                JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = ? AND c.relname = ? AND t.tgname = ? AND NOT t.tgisinternal
            ', ['certificates', $table, "{$table}_reject_delete"]);

            $this->assertNotNull($exists, "reject_delete missing on {$table}");
        }
    }

    #[Test]
    public function award_version_composite_fk_and_core_uniques_work(): void
    {
        $seed = $this->seedAwardAndTemplate();
        $g = $seed['g'];
        $this->bindSchool($g['school_a']);

        $certificateId = (int) DB::table('certificates.certificates')->insertGetId([
            'school_id' => $g['school_a'],
            'enrollment_id' => $g['enrollment_a'],
            'student_id' => $g['student_a'],
            'certificate_type' => 1,
            'created_at' => now(),
        ]);

        $issuanceId = (int) DB::table('certificates.certificate_issuances')->insertGetId([
            'school_id' => $g['school_a'],
            'certificate_id' => $certificateId,
            'issuance_no' => 1,
            'graduation_award_version_id' => $seed['award_version_id'],
            'graduation_award_id' => $seed['award_id'],
            'enrollment_id' => $g['enrollment_a'],
            'template_version_id' => $seed['template_version_id'],
            'lifecycle_status' => 1,
            'certificate_number' => 'CN-001',
            'verification_code' => 'VC-GLOBAL-001',
            'created_at' => now(),
        ]);

        DB::table('certificates.certificate_generation_jobs')->insert([
            'school_id' => $g['school_a'],
            'issuance_id' => $issuanceId,
            'job_status' => 1,
            'attempt_count' => 0,
            'created_at' => now(),
        ]);

        DB::table('certificates.certificate_artifacts')->insert([
            'school_id' => $g['school_a'],
            'issuance_id' => $issuanceId,
            'attempt_no' => 1,
            'storage_key' => 'certs/a1.pdf',
            'content_type' => 'application/pdf',
            'file_hash' => str_repeat('b', 64),
            'byte_size' => 100,
            'generated_at' => now(),
            'generator_version' => 'gen-1',
            'is_current' => true,
            'created_at' => now(),
        ]);

        $this->assertSame(1, DB::table('certificates.certificate_issuances')->where('id', $issuanceId)->count());

        // Cross-school award version pin must fail (composite FK).
        $this->expectException(\Throwable::class);
        DB::table('certificates.certificate_issuances')->insert([
            'school_id' => $g['school_b'],
            'certificate_id' => $certificateId,
            'issuance_no' => 2,
            'graduation_award_version_id' => $seed['award_version_id'],
            'graduation_award_id' => $seed['award_id'],
            'enrollment_id' => $g['enrollment_b'],
            'template_version_id' => $seed['template_version_id'],
            'lifecycle_status' => 1,
            'certificate_number' => 'CN-B',
            'verification_code' => 'VC-CROSS',
            'created_at' => now(),
        ]);
    }

    #[Test]
    public function certificate_number_and_verification_code_uniqueness(): void
    {
        $seed = $this->seedAwardAndTemplate();
        $g = $seed['g'];
        $this->bindSchool($g['school_a']);

        $certificateId = (int) DB::table('certificates.certificates')->insertGetId([
            'school_id' => $g['school_a'],
            'enrollment_id' => $g['enrollment_a'],
            'student_id' => $g['student_a'],
            'certificate_type' => 1,
            'created_at' => now(),
        ]);

        DB::table('certificates.certificate_issuances')->insert([
            'school_id' => $g['school_a'],
            'certificate_id' => $certificateId,
            'issuance_no' => 1,
            'graduation_award_version_id' => $seed['award_version_id'],
            'graduation_award_id' => $seed['award_id'],
            'enrollment_id' => $g['enrollment_a'],
            'template_version_id' => $seed['template_version_id'],
            'lifecycle_status' => 1,
            'certificate_number' => 'CN-DUP',
            'verification_code' => 'VC-DUP',
            'created_at' => now(),
        ]);

        try {
            DB::table('certificates.certificate_issuances')->insert([
                'school_id' => $g['school_a'],
                'certificate_id' => $certificateId,
                'issuance_no' => 2,
                'graduation_award_version_id' => $seed['award_version_id'],
                'graduation_award_id' => $seed['award_id'],
                'enrollment_id' => $g['enrollment_a'],
                'template_version_id' => $seed['template_version_id'],
                'lifecycle_status' => 1,
                'certificate_number' => 'CN-DUP',
                'verification_code' => 'VC-OTHER',
                'created_at' => now(),
            ]);
            $this->fail('Expected duplicate certificate_number to fail');
        } catch (\Throwable) {
            $this->assertTrue(true);
        }

        $this->expectException(\Throwable::class);
        DB::table('certificates.certificate_issuances')->insert([
            'school_id' => $g['school_a'],
            'certificate_id' => $certificateId,
            'issuance_no' => 3,
            'graduation_award_version_id' => $seed['award_version_id'],
            'graduation_award_id' => $seed['award_id'],
            'enrollment_id' => $g['enrollment_a'],
            'template_version_id' => $seed['template_version_id'],
            'lifecycle_status' => 1,
            'certificate_number' => 'CN-OTHER',
            'verification_code' => 'VC-DUP',
            'created_at' => now(),
        ]);
    }

    #[Test]
    public function artifact_current_partial_unique_and_job_unique(): void
    {
        $seed = $this->seedAwardAndTemplate();
        $g = $seed['g'];
        $this->bindSchool($g['school_a']);

        $certificateId = (int) DB::table('certificates.certificates')->insertGetId([
            'school_id' => $g['school_a'],
            'enrollment_id' => $g['enrollment_a'],
            'student_id' => $g['student_a'],
            'certificate_type' => 1,
            'created_at' => now(),
        ]);
        $issuanceId = (int) DB::table('certificates.certificate_issuances')->insertGetId([
            'school_id' => $g['school_a'],
            'certificate_id' => $certificateId,
            'issuance_no' => 1,
            'graduation_award_version_id' => $seed['award_version_id'],
            'graduation_award_id' => $seed['award_id'],
            'enrollment_id' => $g['enrollment_a'],
            'template_version_id' => $seed['template_version_id'],
            'lifecycle_status' => 2,
            'certificate_number' => 'CN-ART',
            'verification_code' => 'VC-ART',
            'issued_at' => now(),
            'created_at' => now(),
        ]);

        DB::table('certificates.certificate_generation_jobs')->insert([
            'school_id' => $g['school_a'],
            'issuance_id' => $issuanceId,
            'job_status' => 3,
            'attempt_count' => 1,
            'created_at' => now(),
        ]);

        DB::table('certificates.certificate_artifacts')->insert([
            'school_id' => $g['school_a'],
            'issuance_id' => $issuanceId,
            'attempt_no' => 1,
            'storage_key' => 'certs/a.pdf',
            'content_type' => 'application/pdf',
            'file_hash' => str_repeat('c', 64),
            'byte_size' => 10,
            'generated_at' => now(),
            'generator_version' => 'g1',
            'is_current' => true,
            'created_at' => now(),
        ]);

        try {
            DB::table('certificates.certificate_artifacts')->insert([
                'school_id' => $g['school_a'],
                'issuance_id' => $issuanceId,
                'attempt_no' => 2,
                'storage_key' => 'certs/b.pdf',
                'content_type' => 'application/pdf',
                'file_hash' => str_repeat('d', 64),
                'byte_size' => 11,
                'generated_at' => now(),
                'generator_version' => 'g1',
                'is_current' => true,
                'created_at' => now(),
            ]);
            $this->fail('Expected second current artifact to fail');
        } catch (\Throwable) {
            $this->assertTrue(true);
        }

        $this->expectException(\Throwable::class);
        DB::table('certificates.certificate_generation_jobs')->insert([
            'school_id' => $g['school_a'],
            'issuance_id' => $issuanceId,
            'job_status' => 1,
            'attempt_count' => 0,
            'created_at' => now(),
        ]);
    }

    #[Test]
    public function hard_delete_of_issuance_is_rejected(): void
    {
        $seed = $this->seedAwardAndTemplate();
        $g = $seed['g'];
        $this->bindSchool($g['school_a']);

        $certificateId = (int) DB::table('certificates.certificates')->insertGetId([
            'school_id' => $g['school_a'],
            'enrollment_id' => $g['enrollment_a'],
            'student_id' => $g['student_a'],
            'certificate_type' => 1,
            'created_at' => now(),
        ]);
        $issuanceId = (int) DB::table('certificates.certificate_issuances')->insertGetId([
            'school_id' => $g['school_a'],
            'certificate_id' => $certificateId,
            'issuance_no' => 1,
            'graduation_award_version_id' => $seed['award_version_id'],
            'graduation_award_id' => $seed['award_id'],
            'enrollment_id' => $g['enrollment_a'],
            'template_version_id' => $seed['template_version_id'],
            'lifecycle_status' => 1,
            'certificate_number' => 'CN-DEL',
            'verification_code' => 'VC-DEL',
            'created_at' => now(),
        ]);

        $this->expectException(\Throwable::class);
        DB::table('certificates.certificate_issuances')->where('id', $issuanceId)->delete();
    }

    #[Test]
    public function template_version_content_is_immutable(): void
    {
        $seed = $this->seedAwardAndTemplate();
        $this->bindSchool($seed['g']['school_a']);

        $this->expectException(\Throwable::class);
        DB::table('certificates.certificate_template_versions')
            ->where('id', $seed['template_version_id'])
            ->update(['content_hash' => str_repeat('f', 64)]);
    }

    #[Test]
    public function rls_actor_under_school_b_cannot_see_school_a_certificates(): void
    {
        $seed = $this->seedAwardAndTemplate();
        $g = $seed['g'];
        $this->bindSchool($g['school_a']);

        $certificateId = (int) DB::table('certificates.certificates')->insertGetId([
            'school_id' => $g['school_a'],
            'enrollment_id' => $g['enrollment_a'],
            'student_id' => $g['student_a'],
            'certificate_type' => 1,
            'created_at' => now(),
        ]);

        PostgreSqlRlsActor::become();
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $g['school_b']]);

        $visible = collect(DB::select('SELECT id FROM certificates.certificates'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertNotContains($certificateId, $visible);

        PostgreSqlRlsActor::reset();
    }

    #[Test]
    public function graduation_tables_were_not_structurally_modified_by_certificates_ddl(): void
    {
        $hasAwardIdSchool = DB::selectOne("
            SELECT 1 AS ok
            FROM pg_indexes
            WHERE schemaname = 'graduation'
              AND indexname = 'graduation_awards_id_school_uidx'
        ");
        $this->assertNull($hasAwardIdSchool, 'Graduation awards must not gain id_school unique from Certificates');

        $this->assertTrue(
            Schema::hasTable(SchemaHelper::qualified('graduation', 'graduation_award_versions'))
        );
    }
}
