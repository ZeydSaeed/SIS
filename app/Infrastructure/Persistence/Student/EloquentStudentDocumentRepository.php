<?php

namespace App\Infrastructure\Persistence\Student;

use App\Database\SchemaHelper;
use App\Domain\Student\Data\StudentDocumentSnapshot;
use App\Domain\Student\Repositories\StudentDocumentRepositoryInterface;
use App\Domain\Student\ValueObjects\StudentDocumentStatus;
use Illuminate\Support\Facades\DB;

final class EloquentStudentDocumentRepository implements StudentDocumentRepositoryInterface
{
    public function studentInSchool(int $schoolId, int $studentId): bool
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('students', 'students'))
            ->where('id', $studentId)
            ->where('school_id', $schoolId)
            ->where('status', 1)
            ->exists();
    }

    public function create(
        int $schoolId,
        int $studentId,
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

        return (int) DB::table(SchemaHelper::qualified('students', 'student_documents'))->insertGetId([
            'school_id' => $schoolId,
            'student_id' => $studentId,
            'document_type' => $documentType,
            'storage_key' => $storageKey,
            'file_name' => $fileName,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'file_hash' => $fileHash,
            'uploaded_by' => $uploadedBy,
            'status' => StudentDocumentStatus::Active->value,
            'created_at' => $createdAt,
        ]);
    }

    public function findActive(int $schoolId, int $documentId): ?StudentDocumentSnapshot
    {
        $this->bindSchool($schoolId);

        $row = DB::table(SchemaHelper::qualified('students', 'student_documents'))
            ->where('id', $documentId)
            ->where('school_id', $schoolId)
            ->where('status', StudentDocumentStatus::Active->value)
            ->first([
                'id', 'school_id', 'student_id', 'document_type', 'storage_key',
                'file_name', 'mime_type', 'file_size', 'file_hash', 'status',
            ]);

        return $row === null ? null : $this->map($row);
    }

    public function findVoided(int $schoolId, int $documentId): ?StudentDocumentSnapshot
    {
        $this->bindSchool($schoolId);

        $row = DB::table(SchemaHelper::qualified('students', 'student_documents'))
            ->where('id', $documentId)
            ->where('school_id', $schoolId)
            ->where('status', StudentDocumentStatus::Voided->value)
            ->first([
                'id', 'school_id', 'student_id', 'document_type', 'storage_key',
                'file_name', 'mime_type', 'file_size', 'file_hash', 'status',
            ]);

        return $row === null ? null : $this->map($row);
    }

    public function listActiveForStudent(int $schoolId, int $studentId): array
    {
        $this->bindSchool($schoolId);

        $rows = DB::table(SchemaHelper::qualified('students', 'student_documents'))
            ->where('school_id', $schoolId)
            ->where('student_id', $studentId)
            ->where('status', StudentDocumentStatus::Active->value)
            ->orderBy('id')
            ->get([
                'id', 'school_id', 'student_id', 'document_type', 'storage_key',
                'file_name', 'mime_type', 'file_size', 'file_hash', 'status',
            ]);

        return $rows->map(fn ($row): StudentDocumentSnapshot => $this->map($row))->all();
    }

    public function void(int $schoolId, int $documentId): bool
    {
        $this->bindSchool($schoolId);

        $updated = DB::table(SchemaHelper::qualified('students', 'student_documents'))
            ->where('id', $documentId)
            ->where('school_id', $schoolId)
            ->where('status', StudentDocumentStatus::Active->value)
            ->update(['status' => StudentDocumentStatus::Voided->value]);

        return $updated > 0;
    }

    public function restore(int $schoolId, int $documentId): bool
    {
        $this->bindSchool($schoolId);

        $updated = DB::table(SchemaHelper::qualified('students', 'student_documents'))
            ->where('id', $documentId)
            ->where('school_id', $schoolId)
            ->where('status', StudentDocumentStatus::Voided->value)
            ->update(['status' => StudentDocumentStatus::Active->value]);

        return $updated > 0;
    }

    private function map(object $row): StudentDocumentSnapshot
    {
        return new StudentDocumentSnapshot(
            id: (int) $row->id,
            schoolId: (int) $row->school_id,
            studentId: (int) $row->student_id,
            documentType: (int) $row->document_type,
            storageKey: (string) $row->storage_key,
            fileName: (string) $row->file_name,
            mimeType: (string) $row->mime_type,
            fileSize: (int) $row->file_size,
            fileHash: (string) $row->file_hash,
            status: (int) $row->status,
        );
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
