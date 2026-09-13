<?php

namespace App\Infrastructure\Persistence\Documents;

use App\Database\SchemaHelper;
use App\Domain\Documents\Data\DocumentFileSnapshot;
use App\Domain\Documents\Repositories\DocumentRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class EloquentDocumentRepository implements DocumentRepositoryInterface
{
    private const COLUMNS = [
        'id',
        'school_id',
        'entity_type',
        'entity_id',
        'document_type',
        'storage_key',
        'file_name',
        'mime_type',
        'file_size',
        'file_hash',
        'uploaded_by',
        'created_at',
    ];

    public function create(
        int $schoolId,
        string $entityType,
        int $entityId,
        int $documentType,
        string $storageKey,
        string $fileName,
        string $mimeType,
        int $fileSize,
        string $fileHash,
        ?int $uploadedBy,
        string $createdAt,
    ): int {
        $this->bindSchool($schoolId);

        return (int) DB::table(SchemaHelper::qualified('documents', 'files'))->insertGetId([
            'school_id' => $schoolId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'document_type' => $documentType,
            'storage_key' => $storageKey,
            'file_name' => $fileName,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'file_hash' => $fileHash,
            'uploaded_by' => $uploadedBy,
            'created_at' => $createdAt,
        ]);
    }

    public function findByIdForSchool(int $schoolId, int $documentId): ?DocumentFileSnapshot
    {
        $this->bindSchool($schoolId);

        $row = DB::table(SchemaHelper::qualified('documents', 'files'))
            ->where('school_id', $schoolId)
            ->where('id', $documentId)
            ->first(self::COLUMNS);

        return $row !== null ? $this->toSnapshot($row) : null;
    }

    public function listByEntity(int $schoolId, string $entityType, int $entityId): array
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('documents', 'files'))
            ->where('school_id', $schoolId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderBy('id')
            ->get(self::COLUMNS)
            ->map(fn (object $row): DocumentFileSnapshot => $this->toSnapshot($row))
            ->all();
    }

    private function toSnapshot(object $row): DocumentFileSnapshot
    {
        return new DocumentFileSnapshot(
            id: (int) $row->id,
            schoolId: (int) $row->school_id,
            entityType: (string) $row->entity_type,
            entityId: (int) $row->entity_id,
            documentType: (int) $row->document_type,
            storageKey: (string) $row->storage_key,
            fileName: (string) $row->file_name,
            mimeType: (string) $row->mime_type,
            fileSize: (int) $row->file_size,
            fileHash: (string) $row->file_hash,
            uploadedBy: $row->uploaded_by !== null ? (int) $row->uploaded_by : null,
            createdAt: (string) $row->created_at,
        );
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
