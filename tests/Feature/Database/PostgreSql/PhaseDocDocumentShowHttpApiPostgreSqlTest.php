<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Domain\Documents\ValueObjects\DocumentType;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseDocDocumentShowHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_show_document_metadata(): void
    {
        $schoolId = $this->createSchool('SCH-DOC-U05', 'DOC Show U05');
        $this->actingAsDocumentsManagerForSchool($schoolId);

        $hash = str_repeat('c', 64);
        $documentId = (int) $this->postJson('/api/v1/documents', [
            'entity_type' => 'student',
            'entity_id' => 201,
            'document_type' => DocumentType::Identity,
            'storage_key' => 'schools/'.$schoolId.'/students/201/id.pdf',
            'file_name' => 'id.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 4096,
            'file_hash' => $hash,
        ], ['X-Idempotency-Key' => 'doc-show-u05'])->json('data.document_id');

        $this->getJson('/api/v1/documents/'.$documentId)
            ->assertOk()
            ->assertJsonPath('data.id', $documentId)
            ->assertJsonPath('data.entity_type', 'student')
            ->assertJsonPath('data.entity_id', 201)
            ->assertJsonPath('data.file_name', 'id.pdf')
            ->assertJsonPath('data.mime_type', 'application/pdf')
            ->assertJsonPath('data.file_hash', $hash);
    }
}
