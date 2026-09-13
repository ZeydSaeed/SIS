<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Documents\ValueObjects\DocumentType;
use App\Domain\Teachers\ValueObjects\QualificationType;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class Phase82QualificationDocumentAttachHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_attach_uploaded_document_to_qualification(): void
    {
        Storage::fake('sis_documents');

        $schoolId = $this->createSchool('SCH-82-1', 'Qual Doc 1');
        $yearId = $this->createAcademicYear('AY-82-1');
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantTeachersManager($user, $schoolId);
        app(SecurityPermissionSeeder::class)->grantDocumentsManager($user, $schoolId);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);

        $teacherId = (int) $this->postJson('/api/v1/teachers', [
            'academic_year_id' => $yearId,
            'employee_code' => 'T-82-1',
            'first_name' => 'Doc',
            'last_name' => 'Link',
        ], ['X-Idempotency-Key' => '82-reg'])->json('data.teacher_id');

        $qualificationId = (int) $this->postJson('/api/v1/teachers/'.$teacherId.'/qualifications', [
            'academic_year_id' => $yearId,
            'qualification_type' => QualificationType::Degree,
            'title' => 'M.Ed',
        ], ['X-Idempotency-Key' => '82-qual'])->json('data.qualification_id');

        $file = UploadedFile::fake()->createWithContent('diploma.pdf', "%PDF-1.4\nqual-doc\n");
        $upload = $this->post('/api/v1/documents/upload', [
            'entity_type' => 'qualification',
            'entity_id' => $qualificationId,
            'document_type' => DocumentType::Qualification,
            'file' => $file,
        ], [
            'X-Idempotency-Key' => '82-doc',
            'Accept' => 'application/json',
        ])->assertCreated();

        $documentId = (int) $upload->json('data.document_id');
        $storageKey = (string) $upload->json('data.storage_key');

        $this->postJson('/api/v1/teachers/'.$teacherId.'/qualifications/'.$qualificationId.'/attach-document', [
            'academic_year_id' => $yearId,
            'document_id' => $documentId,
        ], ['X-Idempotency-Key' => '82-attach'])
            ->assertOk()
            ->assertJsonPath('data.qualification_id', $qualificationId)
            ->assertJsonPath('data.document_storage_key', $storageKey);

        $this->postJson('/api/v1/teachers/'.$teacherId.'/qualifications/'.$qualificationId.'/attach-document', [
            'academic_year_id' => $yearId,
            'document_id' => $documentId,
        ], ['X-Idempotency-Key' => '82-attach'])
            ->assertOk()
            ->assertJsonPath('data.from_idempotency', true);

        $this->assertDatabaseHas(SchemaHelper::qualified('teachers', 'teacher_qualifications'), [
            'id' => $qualificationId,
            'document_storage_key' => $storageKey,
        ]);
    }

    #[Test]
    public function rejects_document_bound_to_other_entity(): void
    {
        Storage::fake('sis_documents');

        $schoolId = $this->createSchool('SCH-82-2', 'QualDoc 2');
        $yearId = $this->createAcademicYear('AY-82-2');
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantTeachersManager($user, $schoolId);
        app(SecurityPermissionSeeder::class)->grantDocumentsManager($user, $schoolId);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);

        $teacherId = (int) $this->postJson('/api/v1/teachers', [
            'academic_year_id' => $yearId,
            'employee_code' => 'T-82-2',
            'first_name' => 'A',
            'last_name' => 'B',
        ], ['X-Idempotency-Key' => '82-reg2'])->json('data.teacher_id');

        $qualificationId = (int) $this->postJson('/api/v1/teachers/'.$teacherId.'/qualifications', [
            'academic_year_id' => $yearId,
            'qualification_type' => QualificationType::Certificate,
            'title' => 'Cert',
        ], ['X-Idempotency-Key' => '82-qual2'])->json('data.qualification_id');

        $file = UploadedFile::fake()->createWithContent('other.txt', "nope\n");
        $documentId = (int) $this->post('/api/v1/documents/upload', [
            'entity_type' => 'teacher',
            'entity_id' => $teacherId,
            'document_type' => DocumentType::Other,
            'file' => $file,
        ], [
            'X-Idempotency-Key' => '82-doc2',
            'Accept' => 'application/json',
        ])->json('data.document_id');

        $this->postJson('/api/v1/teachers/'.$teacherId.'/qualifications/'.$qualificationId.'/attach-document', [
            'academic_year_id' => $yearId,
            'document_id' => $documentId,
        ], ['X-Idempotency-Key' => '82-attach-bad'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'teachers.qualification_document_mismatch');
    }
}
