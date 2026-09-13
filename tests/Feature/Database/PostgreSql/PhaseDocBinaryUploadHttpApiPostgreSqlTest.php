<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Documents\ValueObjects\DocumentType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseDocBinaryUploadHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_upload_and_download_document_binary(): void
    {
        Storage::fake('sis_documents');

        $schoolId = $this->createSchool('SCH-DOC-BIN-1', 'Documents Binary 1');
        $this->actingAsDocumentsManagerForSchool($schoolId);

        $payload = "hello-sis-doc\n";
        $file = UploadedFile::fake()->createWithContent('note.txt', $payload);

        $upload = $this->post('/api/v1/documents/upload', [
            'entity_type' => 'student',
            'entity_id' => 55,
            'document_type' => DocumentType::Other,
            'file' => $file,
        ], [
            'X-Idempotency-Key' => 'doc-bin-1',
            'Accept' => 'application/json',
        ])->assertCreated();

        $documentId = (int) $upload->json('data.document_id');
        $storageKey = (string) $upload->json('data.storage_key');

        $this->assertNotSame('', $storageKey);
        Storage::disk('sis_documents')->assertExists($storageKey);

        $this->post('/api/v1/documents/upload', [
            'entity_type' => 'student',
            'entity_id' => 55,
            'document_type' => DocumentType::Other,
            'file' => UploadedFile::fake()->createWithContent('note.txt', $payload),
        ], [
            'X-Idempotency-Key' => 'doc-bin-1',
            'Accept' => 'application/json',
        ])
            ->assertOk()
            ->assertJsonPath('data.document_id', $documentId)
            ->assertJsonPath('data.from_idempotency', true);

        $this->get('/api/v1/documents/'.$documentId.'/content', [
            'Accept' => 'application/octet-stream',
        ])
            ->assertOk()
            ->assertHeader('X-Content-SHA256', hash('sha256', $payload))
            ->assertSee($payload, false);

        $this->assertDatabaseHas(SchemaHelper::qualified('documents', 'files'), [
            'id' => $documentId,
            'school_id' => $schoolId,
            'entity_type' => 'student',
            'entity_id' => 55,
            'storage_key' => $storageKey,
        ]);
    }

    #[Test]
    public function viewer_can_download_but_cannot_upload(): void
    {
        Storage::fake('sis_documents');

        $schoolId = $this->createSchool('SCH-DOC-BIN-2', 'Documents Binary 2');
        $this->actingAsDocumentsManagerForSchool($schoolId);

        $payload = 'x';
        $documentId = (int) $this->post('/api/v1/documents/upload', [
            'entity_type' => 'teacher',
            'entity_id' => 9,
            'document_type' => DocumentType::Identity,
            'file' => UploadedFile::fake()->createWithContent('id.txt', $payload),
        ], [
            'X-Idempotency-Key' => 'doc-bin-2',
            'Accept' => 'application/json',
        ])->json('data.document_id');

        $this->actingAsDocumentsViewerForSchool($schoolId);

        $this->post('/api/v1/documents/upload', [
            'entity_type' => 'teacher',
            'entity_id' => 9,
            'document_type' => DocumentType::Identity,
            'file' => UploadedFile::fake()->createWithContent('id2.txt', 'y'),
        ], [
            'X-Idempotency-Key' => 'doc-bin-deny',
            'Accept' => 'application/json',
        ])->assertForbidden();

        $this->get('/api/v1/documents/'.$documentId.'/content')
            ->assertOk()
            ->assertSee($payload, false);
    }

    #[Test]
    public function rejects_disallowed_mime_type(): void
    {
        Storage::fake('sis_documents');

        $schoolId = $this->createSchool('SCH-DOC-BIN-3', 'Documents Binary 3');
        $this->actingAsDocumentsManagerForSchool($schoolId);

        $this->post('/api/v1/documents/upload', [
            'entity_type' => 'student',
            'entity_id' => 1,
            'document_type' => DocumentType::Other,
            'file' => UploadedFile::fake()->create('virus.exe', 100, 'application/x-msdownload'),
        ], [
            'X-Idempotency-Key' => 'doc-bin-mime',
            'Accept' => 'application/json',
        ])->assertStatus(422);
    }
}
