<?php

namespace Tests\Feature\Database\PostgreSql;

use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseStuDocShowHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_show_student_document_any_status(): void
    {
        $schoolId = $this->createSchool('SCH-STU-U04', 'STU Doc Show');
        $student = $this->createStudentForSchool($schoolId);
        $this->actingAsStudentManagerForSchool($schoolId);

        $hash = str_repeat('d', 64);
        $documentId = (int) $this->postJson('/api/v1/students/'.$student->id.'/documents', [
            'document_type' => 1,
            'storage_key' => 'schools/'.$schoolId.'/students/'.$student->id.'/id.pdf',
            'file_name' => 'id.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 2048,
            'file_hash' => $hash,
        ], ['X-Idempotency-Key' => 'stu-doc-u04'])->json('data.document_id');

        $this->getJson('/api/v1/student-documents/'.$documentId)
            ->assertOk()
            ->assertJsonPath('data.id', $documentId)
            ->assertJsonPath('data.student_id', $student->id)
            ->assertJsonPath('data.file_name', 'id.pdf')
            ->assertJsonPath('data.status', 1);

        $this->postJson('/api/v1/student-documents/'.$documentId.'/void', [], [
            'X-Idempotency-Key' => 'stu-doc-u04-void',
        ])->assertOk();

        $this->getJson('/api/v1/student-documents/'.$documentId)
            ->assertOk()
            ->assertJsonPath('data.id', $documentId)
            ->assertJsonPath('data.status', 2);
    }
}
