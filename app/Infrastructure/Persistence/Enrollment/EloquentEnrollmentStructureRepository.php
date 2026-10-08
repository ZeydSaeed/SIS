<?php

namespace App\Infrastructure\Persistence\Enrollment;

use App\Database\SchemaHelper;
use App\Domain\Enrollment\Data\ClassSnapshot;
use App\Domain\Enrollment\Data\SectionSnapshot;
use App\Domain\Enrollment\Exceptions\EnrollmentStructureNotFoundException;
use App\Domain\Enrollment\Repositories\EnrollmentStructureRepositoryInterface;
use App\Domain\Enrollment\ValueObjects\EnrollmentStatus;
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

    public function gradeLevelExists(int $gradeLevelId): bool
    {
        return DB::table(SchemaHelper::qualified('academic', 'grade_levels'))
            ->where('id', $gradeLevelId)
            ->exists();
    }

    public function classNameTaken(int $schoolId, int $academicYearId, string $name, ?int $exceptClassId = null): bool
    {
        $this->setSchoolGuc($schoolId);

        return DB::table(SchemaHelper::qualified('enrollment', 'classes'))
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('name', trim($name))
            ->when($exceptClassId !== null, fn ($query) => $query->where('id', '!=', $exceptClassId))
            ->exists();
    }

    public function sectionNameTaken(int $classId, string $name, ?int $exceptSectionId = null): bool
    {
        return DB::table(SchemaHelper::qualified('enrollment', 'sections'))
            ->where('class_id', $classId)
            ->where('name', trim($name))
            ->when($exceptSectionId !== null, fn ($query) => $query->where('id', '!=', $exceptSectionId))
            ->exists();
    }

    public function createClass(int $schoolId, int $academicYearId, int $gradeLevelId, string $name, ?int $capacity, string $at): int
    {
        $this->setSchoolGuc($schoolId);
        $table = SchemaHelper::qualified('enrollment', 'classes');
        $code = $this->nextCode(
            'enrollment.classes:'.$schoolId.':'.$academicYearId,
            'CLS',
            fn (): array => DB::table($table)->where('school_id', $schoolId)->where('academic_year_id', $academicYearId)->pluck('code')->all(),
        );

        return (int) DB::table($table)->insertGetId([
            'school_id' => $schoolId,
            'academic_year_id' => $academicYearId,
            'grade_level_id' => $gradeLevelId,
            'code' => $code,
            'name' => trim($name),
            'capacity' => $capacity,
            'status' => EnrollmentStructureStatus::Active->value,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    public function updateClass(int $schoolId, int $classId, int $gradeLevelId, string $name, ?int $capacity, string $at): void
    {
        $this->setSchoolGuc($schoolId);

        $updated = DB::table(SchemaHelper::qualified('enrollment', 'classes'))
            ->where('id', $classId)
            ->where('school_id', $schoolId)
            ->update([
                'grade_level_id' => $gradeLevelId,
                'name' => trim($name),
                'capacity' => $capacity,
                'updated_at' => $at,
            ]);

        if ($updated === 0) {
            throw EnrollmentStructureNotFoundException::forClass($classId);
        }
    }

    public function createSection(int $schoolId, int $classId, string $name, ?int $capacity, ?int $homeroomTeacherId, string $at): int
    {
        $this->requireClassInSchool($schoolId, $classId, activeOnly: false);
        $table = SchemaHelper::qualified('enrollment', 'sections');
        $code = $this->nextCode(
            'enrollment.sections:'.$classId,
            'SEC',
            fn (): array => DB::table($table)->where('class_id', $classId)->pluck('code')->all(),
        );

        return (int) DB::table($table)->insertGetId([
            'class_id' => $classId,
            'code' => $code,
            'name' => trim($name),
            'capacity' => $capacity,
            'homeroom_teacher_id' => $homeroomTeacherId,
            'status' => EnrollmentStructureStatus::Active->value,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    public function updateSection(int $schoolId, int $sectionId, string $name, ?int $capacity, ?int $homeroomTeacherId, string $at): void
    {
        $this->requireSectionInSchool($schoolId, $sectionId, activeOnly: false);

        DB::table(SchemaHelper::qualified('enrollment', 'sections'))
            ->where('id', $sectionId)
            ->update([
                'name' => trim($name),
                'capacity' => $capacity,
                'homeroom_teacher_id' => $homeroomTeacherId,
                'updated_at' => $at,
            ]);
    }

    public function activeSectionCapacitySum(int $schoolId, int $classId): int
    {
        $this->setSchoolGuc($schoolId);

        return (int) DB::table(SchemaHelper::qualified('enrollment', 'sections'))
            ->where('class_id', $classId)
            ->where('status', EnrollmentStructureStatus::Active->value)
            ->sum('capacity');
    }

    public function countActiveEnrollmentsInClass(int $schoolId, int $classId): int
    {
        $this->setSchoolGuc($schoolId);

        return $this->activeEnrollments($schoolId)->where('class_id', $classId)->count();
    }

    public function countActiveEnrollmentsInSection(int $schoolId, int $sectionId): int
    {
        $this->setSchoolGuc($schoolId);

        return $this->activeEnrollments($schoolId)->where('section_id', $sectionId)->count();
    }

    public function activeEnrollmentCountsBySection(int $schoolId, int $academicYearId): array
    {
        $this->setSchoolGuc($schoolId);

        return $this->activeEnrollments($schoolId)
            ->where('academic_year_id', $academicYearId)
            ->groupBy('section_id')
            ->pluck(DB::raw('COUNT(*) AS total'), 'section_id')
            ->mapWithKeys(fn ($total, $sectionId): array => [(int) $sectionId => (int) $total])
            ->all();
    }

    public function listSectionsForYear(int $schoolId, int $academicYearId): array
    {
        $this->setSchoolGuc($schoolId);

        return DB::table(SchemaHelper::qualified('enrollment', 'sections').' as s')
            ->join(SchemaHelper::qualified('enrollment', 'classes').' as c', 'c.id', '=', 's.class_id')
            ->where('c.school_id', $schoolId)
            ->where('c.academic_year_id', $academicYearId)
            ->orderBy('s.class_id')
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

    /** Active = status ACTIVE and still open (partial unique index enrollments_student_year_active_unique). */
    private function activeEnrollments(int $schoolId): \Illuminate\Database\Query\Builder
    {
        return DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))
            ->where('school_id', $schoolId)
            ->where('status', EnrollmentStatus::ACTIVE)
            ->whereNull('effective_to');
    }

    /**
     * Next PREFIX-n code. The advisory lock serializes concurrent creators of the same
     * sequence until commit, so two inserts cannot pick the same number.
     *
     * @param  callable(): list<mixed>  $existingCodes  read after the lock is held
     */
    private function nextCode(string $lockKey, string $prefix, callable $existingCodes): string
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', [$lockKey]);
        }

        $max = 0;
        foreach ($existingCodes() as $code) {
            if (preg_match('/^'.$prefix.'-(\d+)$/', (string) $code, $matches) === 1) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return sprintf('%s-%d', $prefix, $max + 1);
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
