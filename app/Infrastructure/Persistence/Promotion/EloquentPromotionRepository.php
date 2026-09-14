<?php

namespace App\Infrastructure\Persistence\Promotion;

use App\Database\SchemaHelper;
use App\Domain\Promotion\Data\PromotionRecordSnapshot;
use App\Domain\Promotion\Data\PromotionRuleSnapshot;
use App\Domain\Promotion\Repositories\PromotionRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class EloquentPromotionRepository implements PromotionRepositoryInterface
{
    public function gradeLevelExists(int $gradeLevelId): bool
    {
        return DB::table(SchemaHelper::qualified('academic', 'grade_levels'))
            ->where('id', $gradeLevelId)
            ->exists();
    }

    public function enrollmentBelongsToSchoolYear(int $enrollmentId, int $schoolId, int $academicYearId): bool
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))
            ->where('id', $enrollmentId)
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->exists();
    }

    public function findEnrollmentGradeLevelId(int $enrollmentId, int $schoolId, int $academicYearId): ?int
    {
        $this->bindSchool($schoolId);

        $id = DB::table(SchemaHelper::qualified('enrollment', 'enrollments').' as e')
            ->join(SchemaHelper::qualified('enrollment', 'classes').' as c', 'c.id', '=', 'e.class_id')
            ->where('e.id', $enrollmentId)
            ->where('e.school_id', $schoolId)
            ->where('e.academic_year_id', $academicYearId)
            ->value('c.grade_level_id');

        return $id === null ? null : (int) $id;
    }

    public function findDecisionId(int $schoolId, int $enrollmentId, int $academicYearId): ?int
    {
        $this->bindSchool($schoolId);

        $id = DB::table(SchemaHelper::qualified('promotion', 'records'))
            ->where('school_id', $schoolId)
            ->where('enrollment_id', $enrollmentId)
            ->where('academic_year_id', $academicYearId)
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    public function createRule(
        int $schoolId,
        int $fromGradeLevelId,
        int $toGradeLevelId,
        ?string $minGpa,
        ?int $minPassSubjects,
        ?int $maxFailedSubjects,
        bool $isActive,
        string $createdAt,
    ): int {
        $this->bindSchool($schoolId);

        return (int) DB::table(SchemaHelper::qualified('promotion', 'rules'))->insertGetId([
            'school_id' => $schoolId,
            'from_grade_level_id' => $fromGradeLevelId,
            'to_grade_level_id' => $toGradeLevelId,
            'min_gpa' => $minGpa,
            'min_pass_subjects' => $minPassSubjects,
            'max_failed_subjects' => $maxFailedSubjects,
            'is_active' => $isActive,
            'created_at' => $createdAt,
        ]);
    }

    public function listRules(int $schoolId, ?bool $activeOnly = null): array
    {
        $this->bindSchool($schoolId);

        $q = DB::table(SchemaHelper::qualified('promotion', 'rules'))
            ->where('school_id', $schoolId)
            ->orderBy('id');

        if ($activeOnly === true) {
            $q->where('is_active', true);
        }

        return $q->get([
            'id',
            'school_id',
            'from_grade_level_id',
            'to_grade_level_id',
            'min_gpa',
            'min_pass_subjects',
            'max_failed_subjects',
            'is_active',
            'created_at',
        ])->map(static function (object $row): PromotionRuleSnapshot {
            return new PromotionRuleSnapshot(
                id: (int) $row->id,
                schoolId: (int) $row->school_id,
                fromGradeLevelId: (int) $row->from_grade_level_id,
                toGradeLevelId: (int) $row->to_grade_level_id,
                minGpa: $row->min_gpa !== null ? (string) $row->min_gpa : null,
                minPassSubjects: $row->min_pass_subjects !== null ? (int) $row->min_pass_subjects : null,
                maxFailedSubjects: $row->max_failed_subjects !== null ? (int) $row->max_failed_subjects : null,
                isActive: (bool) $row->is_active,
                createdAt: (string) $row->created_at,
            );
        })->all();
    }

    public function findRule(int $schoolId, int $ruleId): ?PromotionRuleSnapshot
    {
        $this->bindSchool($schoolId);

        $row = DB::table(SchemaHelper::qualified('promotion', 'rules'))
            ->where('school_id', $schoolId)
            ->where('id', $ruleId)
            ->first([
                'id',
                'school_id',
                'from_grade_level_id',
                'to_grade_level_id',
                'min_gpa',
                'min_pass_subjects',
                'max_failed_subjects',
                'is_active',
                'created_at',
            ]);

        if ($row === null) {
            return null;
        }

        return new PromotionRuleSnapshot(
            id: (int) $row->id,
            schoolId: (int) $row->school_id,
            fromGradeLevelId: (int) $row->from_grade_level_id,
            toGradeLevelId: (int) $row->to_grade_level_id,
            minGpa: $row->min_gpa !== null ? (string) $row->min_gpa : null,
            minPassSubjects: $row->min_pass_subjects !== null ? (int) $row->min_pass_subjects : null,
            maxFailedSubjects: $row->max_failed_subjects !== null ? (int) $row->max_failed_subjects : null,
            isActive: (bool) $row->is_active,
            createdAt: (string) $row->created_at,
        );
    }

    public function setRuleActive(int $schoolId, int $ruleId, bool $isActive): void
    {
        $this->bindSchool($schoolId);

        DB::table(SchemaHelper::qualified('promotion', 'rules'))
            ->where('school_id', $schoolId)
            ->where('id', $ruleId)
            ->update(['is_active' => $isActive]);
    }

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
    ): int {
        $this->bindSchool($schoolId);

        return (int) DB::table(SchemaHelper::qualified('promotion', 'records'))->insertGetId([
            'school_id' => $schoolId,
            'enrollment_id' => $enrollmentId,
            'academic_year_id' => $academicYearId,
            'from_grade_level_id' => $fromGradeLevelId,
            'to_grade_level_id' => $toGradeLevelId,
            'promotion_status' => $promotionStatus,
            'gpa_at_promotion' => $gpaAtPromotion,
            'decided_by' => $decidedBy,
            'decided_at' => $decidedAt,
            'notes' => $notes,
            'created_at' => $createdAt,
        ]);
    }

    public function listRecords(int $schoolId, int $academicYearId): array
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('promotion', 'records'))
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->orderBy('id')
            ->get([
                'id',
                'school_id',
                'enrollment_id',
                'academic_year_id',
                'from_grade_level_id',
                'to_grade_level_id',
                'promotion_status',
                'gpa_at_promotion',
                'decided_by',
                'decided_at',
                'notes',
                'created_at',
            ])
            ->map(static function (object $row): PromotionRecordSnapshot {
                return new PromotionRecordSnapshot(
                    id: (int) $row->id,
                    schoolId: (int) $row->school_id,
                    enrollmentId: (int) $row->enrollment_id,
                    academicYearId: (int) $row->academic_year_id,
                    fromGradeLevelId: (int) $row->from_grade_level_id,
                    toGradeLevelId: (int) $row->to_grade_level_id,
                    promotionStatus: (int) $row->promotion_status,
                    gpaAtPromotion: $row->gpa_at_promotion !== null ? (string) $row->gpa_at_promotion : null,
                    decidedBy: $row->decided_by !== null ? (int) $row->decided_by : null,
                    decidedAt: (string) $row->decided_at,
                    notes: $row->notes !== null ? (string) $row->notes : null,
                    createdAt: (string) $row->created_at,
                );
            })
            ->all();
    }

    public function findRecord(int $schoolId, int $recordId): ?PromotionRecordSnapshot
    {
        $this->bindSchool($schoolId);

        $row = DB::table(SchemaHelper::qualified('promotion', 'records'))
            ->where('school_id', $schoolId)
            ->where('id', $recordId)
            ->first([
                'id',
                'school_id',
                'enrollment_id',
                'academic_year_id',
                'from_grade_level_id',
                'to_grade_level_id',
                'promotion_status',
                'gpa_at_promotion',
                'decided_by',
                'decided_at',
                'notes',
                'created_at',
            ]);

        if ($row === null) {
            return null;
        }

        return new PromotionRecordSnapshot(
            id: (int) $row->id,
            schoolId: (int) $row->school_id,
            enrollmentId: (int) $row->enrollment_id,
            academicYearId: (int) $row->academic_year_id,
            fromGradeLevelId: (int) $row->from_grade_level_id,
            toGradeLevelId: (int) $row->to_grade_level_id,
            promotionStatus: (int) $row->promotion_status,
            gpaAtPromotion: $row->gpa_at_promotion !== null ? (string) $row->gpa_at_promotion : null,
            decidedBy: $row->decided_by !== null ? (int) $row->decided_by : null,
            decidedAt: (string) $row->decided_at,
            notes: $row->notes !== null ? (string) $row->notes : null,
            createdAt: (string) $row->created_at,
        );
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
