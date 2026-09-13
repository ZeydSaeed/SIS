<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Student\ValueObjects\StudentDocumentType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseStuDocBinaryUploadHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_upload_and_download_student_document_binary(): void
    {
        Storage::fake('sis_documents');

        $schoolId = $this->createSchool('SCH-STUDOC-BIN1', 'Student Docs Binary 1');
        $student = $this->createStudentForSchool($schoolId);
        $this->actingAsStudentManagerForSchool($schoolId);

        $payload = "student-doc-bytes\n";
        $file = UploadedFile::fake()->createWithContent('id.txt', $payload);

        $upload = $this->post('/api/v1/students/'.$student->id.'/documents/upload', [
            'document_type' => StudentDocumentType::Identity,
            'file' => $file,
        ], [
            'X-Idempotency-Key' => 'studoc-bin-1',
            'Accept' => 'application/json',
        ])->assertCreated();

        $documentId = (int) $upload->json('data.document_id');
        $storageKey = (string) $upload->json('data.storage_key');

        $this->assertNotSame('', $storageKey);
        Storage::disk('sis_documents')->assertExists($storageKey);

        $this->post('/api/v1/students/'.$student->id.'/documents/upload', [
            'document_type' => StudentDocumentType::Identity,
            'file' => UploadedFile::fake()->createWithContent('id.txt', $payload),
        ], [
            'X-Idempotency-Key' => 'studoc-bin-1',
            'Accept' => 'application/json',
        ])
            ->assertOk()
            ->assertJsonPath('data.document_id', $documentId)
            ->assertJsonPath('data.from_idempotency', true);

        $this->get('/api/v1/student-documents/'.$documentId.'/content', [
            'Accept' => 'application/octet-stream',
        ])
            ->assertOk()
            ->assertHeader('X-Content-SHA256', hash('sha256', $payload))
            ->assertSee($payload, false);

        $this->assertDatabaseHas(SchemaHelper::qualified('students', 'student_documents'), [
            'id' => $documentId,
            'school_id' => $schoolId,
            'student_id' => $student->id,
            'storage_key' => $storageKey,
            'status' => 1,
        ]);
    }

    #[Test]
    public function viewer_can_download_but_cannot_upload_student_document(): void
    {
        Storage::fake('sis_documents');

        $schoolId = $this->createSchool('SCH-STUDOC-BIN2', 'Student Docs Binary 2');
        $student = $this->createStudentForSchool($schoolId);
        $this->actingAsStudentManagerForSchool($schoolId);

        $payload = 'x';
        $documentId = (int) $this->post('/api/v1/students/'.$student->id.'/documents/upload', [
            'document_type' => StudentDocumentType::Other,
            'file' => UploadedFile::fake()->createWithContent('note.txt', $payload),
        ], [
            'X-Idempotency-Key' => 'studoc-bin-2',
            'Accept' => 'application/json',
        ])->json('data.document_id');

        $this->actingAsStudentViewer(null, $schoolId);

        $this->post('/api/v1/students/'.$student->id.'/documents/upload', [
            'document_type' => StudentDocumentType::Other,
            'file' => UploadedFile::fake()->createWithContent('note2.txt', 'y'),
        ], [
            'X-Idempotency-Key' => 'studoc-bin-deny',
            'Accept' => 'application/json',
        ])->assertForbidden();

        $this->get('/api/v1/student-documents/'.$documentId.'/content')
            ->assertOk()
            ->assertSee($payload, false);
    }

    #[Test]
    public function rejects_disallowed_mime_type_for_student_document_upload(): void
    {
        Storage::fake('sis_documents');

        $schoolId = $this->createSchool('SCH-STUDOC-BIN3', 'Student Docs Binary 3');
        $student = $this->createStudentForSchool($schoolId);
        $this->actingAsStudentManagerForSchool($schoolId);

        $this->post('/api/v1/students/'.$student->id.'/documents/upload', [
            'document_type' => StudentDocumentType::Other,
            'file' => UploadedFile::fake()->create('virus.exe', 100, 'application/x-msdownload'),
        ], [
            'X-Idempotency-Key' => 'studoc-bin-mime',
            'Accept' => 'application/json',
        ])->assertStatus(422);
    }
}
