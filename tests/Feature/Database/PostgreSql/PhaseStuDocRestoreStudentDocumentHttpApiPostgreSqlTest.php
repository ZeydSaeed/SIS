<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseStuDocRestoreStudentDocumentHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_restore_voided_student_document(): void
    {
        $schoolId = $this->createSchool('SCH-STUDOC-R1', 'Student Docs Restore');
        $student = $this->createStudentForSchool($schoolId);
        $this->actingAsStudentManagerForSchool($schoolId);

        $documentId = (int) $this->postJson('/api/v1/students/'.$student->id.'/documents', [
            'document_type' => 1,
            'storage_key' => 'schools/'.$schoolId.'/students/'.$student->id.'/id-r.pdf',
            'file_name' => 'id-r.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'file_hash' => str_repeat('c', 64),
        ], ['X-Idempotency-Key' => 'studoc-r-reg'])->json('data.document_id');

        $this->postJson('/api/v1/student-documents/'.$documentId.'/void', [], [
            'X-Idempotency-Key' => 'studoc-r-void',
        ])->assertOk();

        $restore = $this->postJson('/api/v1/student-documents/'.$documentId.'/restore', [], [
            'X-Idempotency-Key' => 'studoc-r-on',
        ])->assertOk();

        $this->assertSame($documentId, (int) $restore->json('data.document_id'));
        $this->assertSame(1, (int) $restore->json('data.status'));

        $this->assertDatabaseHas(SchemaHelper::qualified('students', 'student_documents'), [
            'id' => $documentId,
            'status' => 1,
        ]);
    }
}
