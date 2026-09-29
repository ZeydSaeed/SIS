<?php

namespace App\Infrastructure\Persistence\Curriculum;

use App\Database\SchemaHelper;
use App\Domain\Curriculum\Data\CurriculumSnapshot;
use App\Domain\Curriculum\Data\CurriculumSubjectSnapshot;
use App\Domain\Curriculum\Repositories\CurriculumRepositoryInterface;
use App\Domain\Curriculum\ValueObjects\CurriculumStatus;
use Illuminate\Support\Facades\DB;

final class EloquentCurriculumRepository implements CurriculumRepositoryInterface
{
    public function gradeLevelExists(int $gradeLevelId): bool
    {
        return DB::table(SchemaHelper::qualified('academic', 'grade_levels'))
            ->where('id', $gradeLevelId)
            ->where('status', 1)
            ->exists();
    }

    public function academicYearExists(int $academicYearId): bool
    {
        return DB::table(SchemaHelper::qualified('academic', 'academic_years'))
            ->where('id', $academicYearId)
            ->exists();
    }

    public function create(
        int $schoolId,
        int $academicYearId,
        int $gradeLevelId,
        string $name,
        ?int $specializationId,
        string $createdAt,
    ): int {
        $this->bindSchool($schoolId);

        return (int) DB::table(SchemaHelper::qualified('curriculum', 'curricula'))->insertGetId([
            'school_id' => $schoolId,
            'academic_year_id' => $academicYearId,
            'grade_level_id' => $gradeLevelId,
            'specialization_id' => $specializationId,
            'name' => $name,
            'status' => CurriculumStatus::Active->value,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    public function findActiveInSchool(int $schoolId, int $curriculumId): ?CurriculumSnapshot
    {
        $this->bindSchool($schoolId);

        $row = DB::table(SchemaHelper::qualified('curriculum', 'curricula'))
            ->where('id', $curriculumId)
            ->where('school_id', $schoolId)
            ->where('status', CurriculumStatus::Active->value)
            ->first(['id', 'school_id', 'academic_year_id', 'grade_level_id', 'specialization_id', 'name', 'status']);

        return $row === null ? null : $this->mapCurriculum($row);
    }

    public function findInactiveInSchool(int $schoolId, int $curriculumId): ?CurriculumSnapshot
    {
        $this->bindSchool($schoolId);

        $row = DB::table(SchemaHelper::qualified('curriculum', 'curricula'))
            ->where('id', $curriculumId)
            ->where('school_id', $schoolId)
            ->where('status', CurriculumStatus::Inactive->value)
            ->first(['id', 'school_id', 'academic_year_id', 'grade_level_id', 'specialization_id', 'name', 'status']);

        return $row === null ? null : $this->mapCurriculum($row);
    }

    public function listActiveForSchool(int $schoolId, int $academicYearId): array
    {
        $this->bindSchool($schoolId);

        $rows = DB::table(SchemaHelper::qualified('curriculum', 'curricula'))
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('status', CurriculumStatus::Active->value)
            ->orderBy('id')
            ->get(['id', 'school_id', 'academic_year_id', 'grade_level_id', 'specialization_id', 'name', 'status']);

        return $rows->map(fn ($row): CurriculumSnapshot => $this->mapCurriculum($row))->all();
    }

    public function listForSchool(int $schoolId, int $academicYearId): array
    {
        $this->bindSchool($schoolId);

        $rows = DB::table(SchemaHelper::qualified('curriculum', 'curricula'))
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [CurriculumStatus::Active->value])
            ->orderBy('id')
            ->get(['id', 'school_id', 'academic_year_id', 'grade_level_id', 'specialization_id', 'name', 'status']);

        return $rows->map(fn ($row): CurriculumSnapshot => $this->mapCurriculum($row))->all();
    }

    public function searchForSchool(
        int $schoolId,
        int $academicYearId,
        ?int $status,
        ?int $gradeLevelId,
        ?int $specializationId,
        ?int $branchId,
        string $q,
        int $page,
        int $perPage,
    ): array {
        $this->bindSchool($schoolId);

        $curricula = SchemaHelper::qualified('curriculum', 'curricula');
        $specs = SchemaHelper::qualified('vocational', 'specializations');
        $departments = SchemaHelper::qualified('organization', 'departments');

        $query = DB::table($curricula.' as c')
            ->leftJoin($specs.' as sp', 'sp.id', '=', 'c.specialization_id')
            ->leftJoin($departments.' as d', 'd.id', '=', 'sp.department_id')
            ->where('c.school_id', $schoolId)
            ->where('c.academic_year_id', $academicYearId);

        if ($status !== null) {
            $query->where('c.status', $status);
        }
        if ($gradeLevelId !== null) {
            $query->where('c.grade_level_id', $gradeLevelId);
        }
        if ($specializationId !== null) {
            $query->where('c.specialization_id', $specializationId);
        }
        if ($branchId !== null) {
            $query->where('d.branch_id', $branchId);
        }

        $needle = trim($q);
        if ($needle !== '') {
            $like = '%'.$needle.'%';
            $query->where(function ($inner) use ($like): void {
                $inner->where('c.name', 'ilike', $like)
                    ->orWhere('sp.name', 'ilike', $like)
                    ->orWhere('d.name', 'ilike', $like);
            });
        }

        $total = (int) (clone $query)->count('c.id');
        $page = max(1, $page);
        $perPage = min(max(1, $perPage), 100);
        $rows = $query
            ->orderByRaw('CASE WHEN c.status = ? THEN 0 ELSE 1 END', [CurriculumStatus::Active->value])
            ->orderBy('c.id')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get([
                'c.id', 'c.school_id', 'c.academic_year_id', 'c.grade_level_id',
                'c.specialization_id', 'c.name', 'c.status',
            ]);

        return [
            'items' => $rows->map(fn ($row): CurriculumSnapshot => $this->mapCurriculum($row))->all(),
            'total' => $total,
        ];
    }

    public function deactivate(int $schoolId, int $curriculumId): bool
    {
        $this->bindSchool($schoolId);

        $updated = DB::table(SchemaHelper::qualified('curriculum', 'curricula'))
            ->where('id', $curriculumId)
            ->where('school_id', $schoolId)
            ->where('status', CurriculumStatus::Active->value)
            ->update([
                'status' => CurriculumStatus::Inactive->value,
                'updated_at' => (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
            ]);

        return $updated > 0;
    }

    public function reactivate(int $schoolId, int $curriculumId): bool
    {
        $this->bindSchool($schoolId);

        $updated = DB::table(SchemaHelper::qualified('curriculum', 'curricula'))
            ->where('id', $curriculumId)
            ->where('school_id', $schoolId)
            ->where('status', CurriculumStatus::Inactive->value)
            ->update([
                'status' => CurriculumStatus::Active->value,
                'updated_at' => (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
            ]);

        return $updated > 0;
    }

    public function updateSpecialization(
        int $schoolId,
        int $curriculumId,
        ?int $specializationId,
        string $updatedAt,
    ): bool {
        return $this->updateActive($schoolId, $curriculumId, [
            'specialization_id' => $specializationId,
        ], $updatedAt);
    }

    public function updateActive(
        int $schoolId,
        int $curriculumId,
        array $fields,
        string $updatedAt,
    ): bool {
        $this->bindSchool($schoolId);

        $payload = ['updated_at' => $updatedAt];
        if (array_key_exists('name', $fields)) {
            $payload['name'] = $fields['name'];
        }
        if (array_key_exists('specialization_id', $fields)) {
            $payload['specialization_id'] = $fields['specialization_id'];
        }
        if (count($payload) === 1) {
            return false;
        }

        $updated = DB::table(SchemaHelper::qualified('curriculum', 'curricula'))
            ->where('id', $curriculumId)
            ->where('school_id', $schoolId)
            ->where('status', CurriculumStatus::Active->value)
            ->update($payload);

        return $updated > 0;
    }

    public function linkSubject(
        int $schoolId,
        int $curriculumId,
        int $subjectId,
        ?int $weeklyHours,
        bool $isRequired,
        int $subjectOrder,
        string $createdAt,
    ): int {
        $this->bindSchool($schoolId);

        $existing = DB::table(SchemaHelper::qualified('curriculum', 'curriculum_subjects'))
            ->where('curriculum_id', $curriculumId)
            ->where('subject_id', $subjectId)
            ->first(['id', 'status']);

        if ($existing !== null) {
            if ((int) $existing->status !== CurriculumStatus::Active->value) {
                DB::table(SchemaHelper::qualified('curriculum', 'curriculum_subjects'))
                    ->where('id', (int) $existing->id)
                    ->update([
                        'status' => CurriculumStatus::Active->value,
                        'weekly_hours' => $weeklyHours,
                        'is_required' => $isRequired,
                        'subject_order' => $subjectOrder,
                    ]);
            }

            return (int) $existing->id;
        }

        return (int) DB::table(SchemaHelper::qualified('curriculum', 'curriculum_subjects'))->insertGetId([
            'curriculum_id' => $curriculumId,
            'subject_id' => $subjectId,
            'weekly_hours' => $weeklyHours,
            'is_required' => $isRequired,
            'subject_order' => $subjectOrder,
            'status' => CurriculumStatus::Active->value,
            'created_at' => $createdAt,
        ]);
    }

    public function findActiveLink(int $schoolId, int $linkId): ?CurriculumSubjectSnapshot
    {
        $this->bindSchool($schoolId);

        $row = DB::table(SchemaHelper::qualified('curriculum', 'curriculum_subjects').' as cs')
            ->join(SchemaHelper::qualified('curriculum', 'curricula').' as c', 'c.id', '=', 'cs.curriculum_id')
            ->where('cs.id', $linkId)
            ->where('c.school_id', $schoolId)
            ->where('cs.status', CurriculumStatus::Active->value)
            ->first([
                'cs.id', 'cs.curriculum_id', 'cs.subject_id', 'cs.weekly_hours',
                'cs.is_required', 'cs.subject_order', 'cs.status',
            ]);

        return $row === null ? null : $this->mapLink($row);
    }

    public function findInactiveLink(int $schoolId, int $linkId): ?CurriculumSubjectSnapshot
    {
        $this->bindSchool($schoolId);

        $row = DB::table(SchemaHelper::qualified('curriculum', 'curriculum_subjects').' as cs')
            ->join(SchemaHelper::qualified('curriculum', 'curricula').' as c', 'c.id', '=', 'cs.curriculum_id')
            ->where('cs.id', $linkId)
            ->where('c.school_id', $schoolId)
            ->where('cs.status', CurriculumStatus::Inactive->value)
            ->first([
                'cs.id', 'cs.curriculum_id', 'cs.subject_id', 'cs.weekly_hours',
                'cs.is_required', 'cs.subject_order', 'cs.status',
            ]);

        return $row === null ? null : $this->mapLink($row);
    }

    public function listActiveLinks(int $schoolId, int $curriculumId): array
    {
        $this->bindSchool($schoolId);

        $rows = DB::table(SchemaHelper::qualified('curriculum', 'curriculum_subjects'))
            ->where('curriculum_id', $curriculumId)
            ->where('status', CurriculumStatus::Active->value)
            ->orderBy('subject_order')
            ->orderBy('id')
            ->get([
                'id', 'curriculum_id', 'subject_id', 'weekly_hours',
                'is_required', 'subject_order', 'status',
            ]);

        return $rows->map(fn ($row): CurriculumSubjectSnapshot => $this->mapLink($row))->all();
    }

    public function listLinks(int $schoolId, int $curriculumId): array
    {
        $this->bindSchool($schoolId);

        $rows = DB::table(SchemaHelper::qualified('curriculum', 'curriculum_subjects'))
            ->where('curriculum_id', $curriculumId)
            ->orderBy('subject_order')
            ->orderBy('id')
            ->get([
                'id', 'curriculum_id', 'subject_id', 'weekly_hours',
                'is_required', 'subject_order', 'status',
            ]);

        return $rows->map(fn ($row): CurriculumSubjectSnapshot => $this->mapLink($row))->all();
    }

    public function deactivateLink(int $schoolId, int $linkId): bool
    {
        $this->bindSchool($schoolId);

        $link = $this->findActiveLink($schoolId, $linkId);
        if ($link === null) {
            return false;
        }

        $updated = DB::table(SchemaHelper::qualified('curriculum', 'curriculum_subjects'))
            ->where('id', $linkId)
            ->where('status', CurriculumStatus::Active->value)
            ->update(['status' => CurriculumStatus::Inactive->value]);

        return $updated > 0;
    }

    public function reactivateLink(int $schoolId, int $linkId): bool
    {
        $this->bindSchool($schoolId);

        $link = $this->findInactiveLink($schoolId, $linkId);
        if ($link === null) {
            return false;
        }

        $updated = DB::table(SchemaHelper::qualified('curriculum', 'curriculum_subjects'))
            ->where('id', $linkId)
            ->where('status', CurriculumStatus::Inactive->value)
            ->update(['status' => CurriculumStatus::Active->value]);

        return $updated > 0;
    }

    private function mapCurriculum(object $row): CurriculumSnapshot
    {
        return new CurriculumSnapshot(
            id: (int) $row->id,
            schoolId: (int) $row->school_id,
            academicYearId: (int) $row->academic_year_id,
            gradeLevelId: (int) $row->grade_level_id,
            specializationId: $row->specialization_id !== null ? (int) $row->specialization_id : null,
            name: (string) $row->name,
            status: (int) $row->status,
        );
    }

    private function mapLink(object $row): CurriculumSubjectSnapshot
    {
        return new CurriculumSubjectSnapshot(
            id: (int) $row->id,
            curriculumId: (int) $row->curriculum_id,
            subjectId: (int) $row->subject_id,
            weeklyHours: $row->weekly_hours !== null ? (int) $row->weekly_hours : null,
            isRequired: (bool) $row->is_required,
            subjectOrder: (int) $row->subject_order,
            status: (int) $row->status,
        );
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
