<?php

namespace App\Domain\Promotion\Repositories;

use App\Domain\Promotion\Data\PromotionRecordSnapshot;
use App\Domain\Promotion\Data\PromotionRuleSnapshot;

interface PromotionRepositoryInterface
{
    public function gradeLevelExists(int $gradeLevelId): bool;

    public function enrollmentBelongsToSchoolYear(int $enrollmentId, int $schoolId, int $academicYearId): bool;

    public function findEnrollmentGradeLevelId(int $enrollmentId, int $schoolId, int $academicYearId): ?int;

    public function findDecisionId(int $schoolId, int $enrollmentId, int $academicYearId): ?int;

    public function createRule(
        int $schoolId,
        int $fromGradeLevelId,
        int $toGradeLevelId,
        ?string $minGpa,
        ?int $minPassSubjects,
        ?int $maxFailedSubjects,
        bool $isActive,
        string $createdAt,
    ): int;

    /**
     * @return list<PromotionRuleSnapshot>
     */
    public function listRules(int $schoolId, ?bool $activeOnly = null): array;

    public function findRule(int $schoolId, int $ruleId): ?PromotionRuleSnapshot;

    public function setRuleActive(int $schoolId, int $ruleId, bool $isActive): void;

    public function createRecord(
        int $schoolId,
        int $enrollmentId,
        int $academicYearId,
        int $fromGradeLevelId,
        int $toGradeLevelId,
        int $promotionStatus,
        ?string $gpaAtPromotion,
        ?int $decidedBy,
        string $decidedAt,
        ?string $notes,
        string $createdAt,
    ): int;

    /**
     * @return list<PromotionRecordSnapshot>
     */
    public function listRecords(int $schoolId, int $academicYearId): array;
}
