<?php

namespace App\Infrastructure\Persistence\Student;

use App\Application\Student\Contracts\StudentReadRepositoryInterface;
use App\Application\Student\DTOs\StudentDetailDTO;
use App\Application\Student\DTOs\StudentListItemDTO;
use App\Database\SchemaHelper;
use App\Infrastructure\Persistence\Eloquent\StudentRecord;
use Illuminate\Database\Eloquent\Builder;

final class EloquentStudentManagementReadRepository implements StudentReadRepositoryInterface
{
    public function findDetail(int $studentId): ?StudentDetailDTO
    {
        $record = StudentRecord::query()->find($studentId);

        if ($record === null) {
            return null;
        }

        return $this->mapDetail($record);
    }

    public function paginate(?int $status, int $page, int $perPage): array
    {
        $query = StudentRecord::query()->orderByDesc('id');

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $this->paginateQuery($query, $page, $perPage);
    }

    public function search(string $term, int $page, int $perPage): array
    {
        $term = trim($term);
        $query = StudentRecord::query()->orderBy('full_name');

        if ($term !== '') {
            $likeOperator = SchemaHelper::isPostgreSql() ? 'ilike' : 'like';
            $pattern = '%'.$term.'%';

            $query->where(function (Builder $builder) use ($likeOperator, $pattern): void {
                $builder->where('full_name', $likeOperator, $pattern)
                    ->orWhere('student_code', $likeOperator, $pattern)
                    ->orWhere('national_id', $likeOperator, $pattern)
                    ->orWhere('first_name', $likeOperator, $pattern)
                    ->orWhere('last_name', $likeOperator, $pattern);
            });
        }

        return $this->paginateQuery($query, $page, $perPage);
    }

    /**
     * @return array{items: list<StudentListItemDTO>, pagination: array{page: int, per_page: int, total: int, last_page: int}}
     */
    private function paginateQuery(Builder $query, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = min(max(1, $perPage), 100);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        /** @var list<StudentListItemDTO> $items */
        $items = collect($paginator->items())
            ->map(fn (StudentRecord $record): StudentListItemDTO => $this->mapListItem($record))
            ->all();

        return [
            'items' => $items,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    private function mapListItem(StudentRecord $record): StudentListItemDTO
    {
        return new StudentListItemDTO(
            id: (int) $record->getKey(),
            studentCode: (string) $record->student_code,
            fullName: (string) $record->full_name,
            status: (int) $record->status,
            gender: (int) $record->gender,
            birthDate: $record->birth_date->format('Y-m-d'),
        );
    }

    private function mapDetail(StudentRecord $record): StudentDetailDTO
    {
        return new StudentDetailDTO(
            id: (int) $record->getKey(),
            publicId: $record->public_id,
            studentCode: (string) $record->student_code,
            nationalId: $record->national_id,
            firstName: (string) $record->first_name,
            middleName: $record->middle_name,
            lastName: (string) $record->last_name,
            fullName: (string) $record->full_name,
            gender: (int) $record->gender,
            birthDate: $record->birth_date->format('Y-m-d'),
            birthPlace: $record->birth_place,
            nationality: $record->nationality,
            status: (int) $record->status,
            createdAt: $record->created_at->toIso8601String(),
            updatedAt: $record->updated_at->toIso8601String(),
        );
    }
}
