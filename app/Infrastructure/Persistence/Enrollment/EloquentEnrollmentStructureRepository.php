<?php

namespace App\Infrastructure\Persistence\Enrollment;

use App\Database\SchemaHelper;
use App\Domain\Enrollment\Data\ClassSnapshot;
use App\Domain\Enrollment\Data\SectionSnapshot;
use App\Domain\Enrollment\Exceptions\EnrollmentStructureNotFoundException;
use App\Domain\Enrollment\Repositories\EnrollmentStructureRepositoryInterface;
use App\Domain\Enrollment\ValueObjects\EnrollmentStructureStatus;
use Illuminate\Support\Facades\DB;

final class EloquentEnrollmentStructureRepository implements EnrollmentStructureRepositoryInterface
{
    public function listClasses(int $schoolId, ?int $academicYearId = null): array
    {
        $this->setSchoolGuc($schoolId);

        $query = DB::table(SchemaHelper::qualified('enrollment', 'classes'))
            ->where('school_id', $schoolId)
            ->orderBy('code')
            ->orderBy('id');

        if ($academicYearId !== null) {
            $query->where('academic_year_id', $academicYearId);
        }

        return $query
            ->get([
                'id',
                'school_id',
                'academic_year_id',
                'grade_level_id',
                'code',
                'name',
                'capacity',
                'status',
                'created_at',
                'updated_at',
            ])
            ->map(fn (object $row): ClassSnapshot => $this->mapClass($row))
            ->all();
    }

    public function findClass(int $schoolId, int $classId): ?ClassSnapshot
    {
        $this->setSchoolGuc($schoolId);

        $row = DB::table(SchemaHelper::qualified('enrollment', 'classes'))
            ->where('id', $classId)
            ->where('school_id', $schoolId)
            ->first([
                'id',
                'school_id',
                'academic_year_id',
                'grade_level_id',
                'code',
                'name',
                'capacity',
                'status',
                'created_at',
                'updated_at',
            ]);

        return $row === null ? null : $this->mapClass($row);
    }

    public function listSectionsForClass(int $schoolId, int $classId): ?array
    {
        if ($this->findClass($schoolId, $classId) === null) {
            return null;
        }

        $this->setSchoolGuc($schoolId);

        return DB::table(SchemaHelper::qualified('enrollment', 'sections').' as s')
            ->join(SchemaHelper::qualified('enrollment', 'classes').' as c', 'c.id', '=', 's.class_id')
            ->where('s.class_id', $classId)
            ->where('c.school_id', $schoolId)
            ->orderBy('s.code')
            ->orderBy('s.id')
            ->get([
                's.id',
                's.class_id',
                'c.school_id',
                's.code',
                's.name',
                's.capacity',
                's.homeroom_teacher_id',
                's.status',
                's.created_at',
                's.updated_at',
            ])
            ->map(fn (object $row): SectionSnapshot => $this->mapSection($row))
            ->all();
    }

    public function findSection(int $schoolId, int $sectionId): ?SectionSnapshot
    {
        $this->setSchoolGuc($schoolId);

        $row = DB::table(SchemaHelper::qualified('enrollment', 'sections').' as s')
            ->join(SchemaHelper::qualified('enrollment', 'classes').' as c', 'c.id', '=', 's.class_id')
            ->where('s.id', $sectionId)
            ->where('c.school_id', $schoolId)
            ->first([
                's.id',
                's.class_id',
                'c.school_id',
                's.code',
                's.name',
                's.capacity',
                's.homeroom_teacher_id',
                's.status',
                's.created_at',
                's.updated_at',
            ]);

        return $row === null ? null : $this->mapSection($row);
    }

    public function deactivateClass(int $schoolId, int $classId, string $at): void
    {
        $this->requireClassInSchool($schoolId, $classId, activeOnly: true);
        $this->setSchoolGuc($schoolId);

        $updated = DB::table(SchemaHelper::qualified('enrollment', 'classes'))
            ->where('id', $classId)
            ->where('school_id', $schoolId)
            ->where('status', EnrollmentStructureStatus::Active->value)
            ->update([
                'status' => EnrollmentStructureStatus::Inactive->value,
                'updated_at' => $at,
            ]);

        if ($updated === 0) {
            throw EnrollmentStructureNotFoundException::forClass($classId);
        }
    }

    public function reactivateClass(int $schoolId, int $classId, string $at): void
    {
        $this->requireClassInSchool($schoolId, $classId, activeOnly: false);
        $this->setSchoolGuc($schoolId);

        $updated = DB::table(SchemaHelper::qualified('enrollment', 'classes'))
            ->where('id', $classId)
            ->where('school_id', $schoolId)
            ->where('status', EnrollmentStructureStatus::Inactive->value)
            ->update([
                'status' => EnrollmentStructureStatus::Active->value,
                'updated_at' => $at,
            ]);

        if ($updated === 0) {
            throw EnrollmentStructureNotFoundException::forClass($classId);
        }
    }

    public function deactivateSection(int $schoolId, int $sectionId, string $at): void
    {
        $this->requireSectionInSchool($schoolId, $sectionId, activeOnly: true);
        $this->setSchoolGuc($schoolId);

        $updated = DB::table(SchemaHelper::qualified('enrollment', 'sections'))
            ->where('id', $sectionId)
            ->where('status', EnrollmentStructureStatus::Active->value)
            ->whereIn('class_id', function ($query) use ($schoolId): void {
                $query->select('id')
                    ->from(SchemaHelper::qualified('enrollment', 'classes'))
                    ->where('school_id', $schoolId);
            })
            ->update([
                'status' => EnrollmentStructureStatus::Inactive->value,
                'updated_at' => $at,
            ]);

        if ($updated === 0) {
            throw EnrollmentStructureNotFoundException::forSection($sectionId);
        }
    }

    public function reactivateSection(int $schoolId, int $sectionId, string $at): void
    {
        $this->requireSectionInSchool($schoolId, $sectionId, activeOnly: false);
        $this->setSchoolGuc($schoolId);

        $updated = DB::table(SchemaHelper::qualified('enrollment', 'sections'))
            ->where('id', $sectionId)
            ->where('status', EnrollmentStructureStatus::Inactive->value)
            ->whereIn('class_id', function ($query) use ($schoolId): void {
                $query->select('id')
                    ->from(SchemaHelper::qualified('enrollment', 'classes'))
                    ->where('school_id', $schoolId);
            })
            ->update([
                'status' => EnrollmentStructureStatus::Active->value,
                'updated_at' => $at,
            ]);

        if ($updated === 0) {
            throw EnrollmentStructureNotFoundException::forSection($sectionId);
        }
    }

    private function requireClassInSchool(int $schoolId, int $classId, bool $activeOnly): void
    {
        $this->setSchoolGuc($schoolId);

        $query = DB::table(SchemaHelper::qualified('enrollment', 'classes'))
            ->where('id', $classId)
            ->where('school_id', $schoolId);

        if ($activeOnly) {
            $query->where('status', EnrollmentStructureStatus::Active->value);
        }

        if ($query->doesntExist()) {
            throw EnrollmentStructureNotFoundException::forClass($classId);
        }
    }

    private function requireSectionInSchool(int $schoolId, int $sectionId, bool $activeOnly): void
    {
        $this->setSchoolGuc($schoolId);

        $query = DB::table(SchemaHelper::qualified('enrollment', 'sections').' as s')
            ->join(SchemaHelper::qualified('enrollment', 'classes').' as c', 'c.id', '=', 's.class_id')
            ->where('s.id', $sectionId)
            ->where('c.school_id', $schoolId);

        if ($activeOnly) {
            $query->where('s.status', EnrollmentStructureStatus::Active->value);
        }

        if ($query->doesntExist()) {
            throw EnrollmentStructureNotFoundException::forSection($sectionId);
        }
    }

    private function mapClass(object $row): ClassSnapshot
    {
        return new ClassSnapshot(
            id: (int) $row->id,
            schoolId: (int) $row->school_id,
            academicYearId: (int) $row->academic_year_id,
            gradeLevelId: (int) $row->grade_level_id,
            code: (string) $row->code,
            name: (string) $row->name,
            capacity: $row->capacity !== null ? (int) $row->capacity : null,
            status: (int) $row->status,
            createdAt: (string) $row->created_at,
            updatedAt: (string) $row->updated_at,
        );
    }

    private function mapSection(object $row): SectionSnapshot
    {
        return new SectionSnapshot(
            id: (int) $row->id,
            classId: (int) $row->class_id,
            schoolId: (int) $row->school_id,
            code: (string) $row->code,
            name: (string) $row->name,
            capacity: $row->capacity !== null ? (int) $row->capacity : null,
            homeroomTeacherId: $row->homeroom_teacher_id !== null ? (int) $row->homeroom_teacher_id : null,
            status: (int) $row->status,
            createdAt: (string) $row->created_at,
            updatedAt: (string) $row->updated_at,
        );
    }

    private function setSchoolGuc(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
