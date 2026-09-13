<?php

namespace App\Infrastructure\Persistence\Curriculum;

use App\Database\SchemaHelper;
use App\Domain\Curriculum\Data\SubjectSnapshot;
use App\Domain\Curriculum\Repositories\SubjectRepositoryInterface;
use App\Domain\Curriculum\ValueObjects\SubjectStatus;
use Illuminate\Support\Facades\DB;

final class EloquentSubjectRepository implements SubjectRepositoryInterface
{
    public function codeExists(string $code): bool
    {
        return DB::table(SchemaHelper::qualified('curriculum', 'subjects'))
            ->where('code', $code)
            ->exists();
    }

    public function create(
        string $code,
        string $name,
        ?string $nameEn,
        int $subjectType,
        ?int $creditHours,
        int $maxGrade,
        int $passGrade,
        string $createdAt,
    ): int {
        return (int) DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->insertGetId([
            'code' => $code,
            'name' => $name,
            'name_en' => $nameEn,
            'subject_type' => $subjectType,
            'credit_hours' => $creditHours,
            'max_grade' => $maxGrade,
            'pass_grade' => $passGrade,
            'status' => SubjectStatus::Active->value,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    public function findActive(int $subjectId): ?SubjectSnapshot
    {
        $row = DB::table(SchemaHelper::qualified('curriculum', 'subjects'))
            ->where('id', $subjectId)
            ->where('status', SubjectStatus::Active->value)
            ->first([
                'id', 'code', 'name', 'name_en', 'subject_type', 'credit_hours',
                'max_grade', 'pass_grade', 'status',
            ]);

        return $row === null ? null : $this->map($row);
    }

    public function findInactive(int $subjectId): ?SubjectSnapshot
    {
        $row = DB::table(SchemaHelper::qualified('curriculum', 'subjects'))
            ->where('id', $subjectId)
            ->where('status', SubjectStatus::Inactive->value)
            ->first([
                'id', 'code', 'name', 'name_en', 'subject_type', 'credit_hours',
                'max_grade', 'pass_grade', 'status',
            ]);

        return $row === null ? null : $this->map($row);
    }

    public function listActive(): array
    {
        $rows = DB::table(SchemaHelper::qualified('curriculum', 'subjects'))
            ->where('status', SubjectStatus::Active->value)
            ->orderBy('code')
            ->get([
                'id', 'code', 'name', 'name_en', 'subject_type', 'credit_hours',
                'max_grade', 'pass_grade', 'status',
            ]);

        return $rows->map(fn ($row): SubjectSnapshot => $this->map($row))->all();
    }

    public function deactivate(int $subjectId): bool
    {
        $updated = DB::table(SchemaHelper::qualified('curriculum', 'subjects'))
            ->where('id', $subjectId)
            ->where('status', SubjectStatus::Active->value)
            ->update([
                'status' => SubjectStatus::Inactive->value,
                'updated_at' => (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
            ]);

        return $updated > 0;
    }

    public function reactivate(int $subjectId): bool
    {
        $updated = DB::table(SchemaHelper::qualified('curriculum', 'subjects'))
            ->where('id', $subjectId)
            ->where('status', SubjectStatus::Inactive->value)
            ->update([
                'status' => SubjectStatus::Active->value,
                'updated_at' => (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
            ]);

        return $updated > 0;
    }

    public function updateActive(int $subjectId, array $fields, string $updatedAt): bool
    {
        $payload = ['updated_at' => $updatedAt];
        foreach (['name', 'name_en', 'subject_type', 'credit_hours', 'max_grade', 'pass_grade'] as $key) {
            if (array_key_exists($key, $fields)) {
                $payload[$key] = $fields[$key];
            }
        }

        $updated = DB::table(SchemaHelper::qualified('curriculum', 'subjects'))
            ->where('id', $subjectId)
            ->where('status', SubjectStatus::Active->value)
            ->update($payload);

        return $updated > 0;
    }

    private function map(object $row): SubjectSnapshot
    {
        return new SubjectSnapshot(
            id: (int) $row->id,
            code: (string) $row->code,
            name: (string) $row->name,
            nameEn: $row->name_en !== null ? (string) $row->name_en : null,
            subjectType: (int) $row->subject_type,
            creditHours: $row->credit_hours !== null ? (int) $row->credit_hours : null,
            maxGrade: (int) $row->max_grade,
            passGrade: (int) $row->pass_grade,
            status: (int) $row->status,
        );
    }
}
