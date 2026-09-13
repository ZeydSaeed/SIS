<?php

namespace App\Domain\Teachers\Services;

use App\Domain\Documents\Data\DocumentFileSnapshot;
use App\Domain\Documents\Repositories\DocumentRepositoryInterface;
use App\Domain\Teachers\Data\TeacherQualificationSnapshot;
use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;
use App\Domain\Teachers\ValueObjects\QualificationStatus;

/**
 * Attach qualification document preconditions — keeps Application handler within ARCH-103.
 */
final class AttachQualificationDocumentGuard
{
    public function __construct(
        private readonly TeacherRepositoryInterface $teachers,
        private readonly DocumentRepositoryInterface $documents,
    ) {}

    /**
     * @return array{0: ?string, 1: ?TeacherQualificationSnapshot, 2: ?DocumentFileSnapshot}
     */
    public function evaluate(
        int $schoolId,
        int $teacherId,
        int $qualificationId,
        int $documentId,
        int $academicYearId,
    ): array {
        if ($academicYearId < 1) {
            return ['teachers.academic_year_invalid', null, null];
        }
        if (! $this->teachers->belongsToSchool($teacherId, $schoolId, $academicYearId)) {
            return ['teachers.not_in_school_year', null, null];
        }

        $qual = $this->teachers->findQualification($teacherId, $qualificationId);
        if ($qual === null) {
            return ['teachers.qualification_not_found', null, null];
        }
        if (! QualificationStatus::isActive($qual->status)) {
            return ['teachers.qualification_not_active', null, null];
        }

        $doc = $this->documents->findByIdForSchool($schoolId, $documentId);
        if ($doc === null) {
            return ['teachers.qualification_document_not_found', null, null];
        }
        if (strtolower($doc->entityType) !== 'qualification' || $doc->entityId !== $qualificationId) {
            return ['teachers.qualification_document_mismatch', null, null];
        }
        if (trim($doc->storageKey) === '') {
            return ['teachers.qualification_document_storage_missing', null, null];
        }

        return [null, $qual, $doc];
    }
}
