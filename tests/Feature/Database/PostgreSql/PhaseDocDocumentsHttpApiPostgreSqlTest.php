<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Documents\ValueObjects\DocumentType;
use App\Security\Authorization\Permission;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseDocDocumentsHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function documents_table_has_force_rls_and_school_id(): void
    {
        $cols = collect(DB::select("
            SELECT column_name FROM information_schema.columns
            WHERE table_schema = 'documents' AND table_name = 'files'
        "))->pluck('column_name')->all();

        $this->assertContains('school_id', $cols);

        $row = DB::selectOne("
            SELECT c.relrowsecurity AS rls_enabled, c.relforcerowsecurity AS rls_forced
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'documents' AND c.relname = 'files'
        ");
        $this->assertTrue((bool) $row->rls_enabled);
        $this->assertTrue((bool) $row->rls_forced);
    }

    #[Test]
    public function documents_permissions_are_registered(): void
    {
        $this->assertArrayHasKey(Permission::DOCUMENTS_VIEW, config('security.permissions'));
        $this->assertArrayHasKey(Permission::DOCUMENTS_MANAGE, config('security.permissions'));
    }

    #[Test]
    public function manager_can_register_and_list_document_metadata(): void
    {
        $schoolId = $this->createSchool('SCH-DOC-1', 'Documents 1');
        $this->actingAsDocumentsManagerForSchool($schoolId);

        $hash = str_repeat('a', 64);
        $create = $this->postJson('/api/v1/documents', [
            'entity_type' => 'student',
            'entity_id' => 101,
            'document_type' => DocumentType::Identity,
            'storage_key' => 'schools/'.$schoolId.'/students/101/id.pdf',
            'file_name' => 'id.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 2048,
            'file_hash' => $hash,
        ], ['X-Idempotency-Key' => 'doc-reg-1'])
            ->assertCreated();

        $documentId = (int) $create->json('data.document_id');

        $this->postJson('/api/v1/documents', [
            'entity_type' => 'student',
            'entity_id' => 101,
            'document_type' => DocumentType::Identity,
            'storage_key' => 'schools/'.$schoolId.'/students/101/id.pdf',
            'file_name' => 'id.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 2048,
            'file_hash' => $hash,
        ], ['X-Idempotency-Key' => 'doc-reg-1'])
            ->assertOk()
            ->assertJsonPath('data.document_id', $documentId)
            ->assertJsonPath('data.from_idempotency', true);

        $this->getJson('/api/v1/documents?entity_type=student&entity_id=101')
            ->assertOk()
            ->assertJsonPath('data.0.id', $documentId)
            ->assertJsonPath('data.0.storage_key', 'schools/'.$schoolId.'/students/101/id.pdf');

        $this->assertDatabaseHas(SchemaHelper::qualified('documents', 'files'), [
            'id' => $documentId,
            'school_id' => $schoolId,
            'entity_type' => 'student',
            'entity_id' => 101,
        ]);
    }

    #[Test]
    public function viewer_cannot_register_document(): void
    {
        $schoolId = $this->createSchool('SCH-DOC-2', 'Documents 2');
        $this->actingAsDocumentsViewerForSchool($schoolId);

        $this->postJson('/api/v1/documents', [
            'entity_type' => 'teacher',
            'entity_id' => 1,
            'document_type' => DocumentType::Other,
            'storage_key' => 'k',
            'file_name' => 'a.txt',
            'mime_type' => 'text/plain',
            'file_size' => 1,
            'file_hash' => str_repeat('b', 64),
        ], ['X-Idempotency-Key' => 'doc-deny'])
            ->assertForbidden();
    }
}
