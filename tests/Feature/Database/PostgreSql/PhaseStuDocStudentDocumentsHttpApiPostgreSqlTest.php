<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseStuDocStudentDocumentsHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function student_documents_have_force_rls(): void
    {
        $row = DB::selectOne("
            SELECT c.relrowsecurity AS rls, c.relforcerowsecurity AS force_rls
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'students' AND c.relname = 'student_documents'
        ");
        $this->assertNotNull($row);
        $this->assertTrue((bool) $row->rls);
        $this->assertTrue((bool) $row->force_rls);
    }

    #[Test]
    public function manager_can_register_list_and_void_student_document(): void
    {
        $schoolId = $this->createSchool('SCH-STUDOC1', 'Student Docs School');
        $student = $this->createStudentForSchool($schoolId);
        $this->actingAsStudentManagerForSchool($schoolId);

        $hash = str_repeat('b', 64);
        $documentId = (int) $this->postJson('/api/v1/students/'.$student->id.'/documents', [
            'document_type' => 1,
            'storage_key' => 'schools/'.$schoolId.'/students/'.$student->id.'/id.pdf',
            'file_name' => 'id.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'file_hash' => $hash,
        ], ['X-Idempotency-Key' => 'studoc-reg-1'])
            ->assertCreated()
            ->json('data.document_id');

        $this->postJson('/api/v1/students/'.$student->id.'/documents', [
            'document_type' => 1,
            'storage_key' => 'schools/'.$schoolId.'/students/'.$student->id.'/id.pdf',
            'file_name' => 'id.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'file_hash' => $hash,
        ], ['X-Idempotency-Key' => 'studoc-reg-1'])
            ->assertOk()
            ->assertJsonPath('data.from_idempotency', true);

        $this->getJson('/api/v1/students/'.$student->id.'/documents')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $documentId);

        $this->postJson('/api/v1/student-documents/'.$documentId.'/void', [], [
            'X-Idempotency-Key' => 'studoc-void-1',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 2);

        $this->assertDatabaseHas(SchemaHelper::qualified('students', 'student_documents'), [
            'id' => $documentId,
            'status' => 2,
        ]);

        $this->getJson('/api/v1/students/'.$student->id.'/documents')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
