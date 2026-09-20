<?php

namespace App\Infrastructure\Persistence\Student;

use App\Application\Student\Contracts\StudentReadRepositoryInterface;
use App\Application\Student\DTOs\StudentDetailDTO;
use App\Application\Student\DTOs\StudentListItemDTO;
use App\Database\SchemaHelper;
use App\Domain\Student\ValueObjects\StudentReligion;
use App\Infrastructure\Persistence\Eloquent\StudentRecord;
use Illuminate\Database\Eloquent\Builder;

final class EloquentStudentManagementReadRepository implements StudentReadRepositoryInterface
{
    /**
     * @var list<string>
     */
    private const LIST_COLUMNS = [
        'id',
        'student_code',
        'full_name',
        'first_name',
        'father_name',
        'grandfather_name',
        'great_grandfather_name',
        'last_name',
        'mother_name',
        'maternal_father_name',
        'maternal_grandfather_name',
        'guardian_triple_name',
        'governorate',
        'neighborhood',
        'locality',
        'house_number',
        'birth_date',
        'birth_place',
        'registration_place',
        'gender',
        'nationality',
        'religion',
        'mawalid_date',
        'national_id',
        'previous_school_name',
        'transfer_document_number',
        'transfer_document_date',
        'school_start_date',
        'admitted_class_name',
        'notes',
        'mobile',
        'guardian_mobile',
        'email',
        'school_name',
        'department_name',
        'specialization_name',
        'stage_name',
        'section_name',
        'status',
    ];

    /**
     * @var list<string>
     */
    private const DETAIL_COLUMNS = [
        'id',
        'public_id',
        'student_code',
        'national_id',
        'first_name',
        'middle_name',
        'father_name',
        'grandfather_name',
        'great_grandfather_name',
        'last_name',
        'mother_name',
        'maternal_father_name',
        'maternal_grandfather_name',
        'full_name',
        'guardian_triple_name',
        'gender',
        'birth_date',
        'mawalid_date',
        'birth_place',
        'nationality',
        'governorate',
        'neighborhood',
        'locality',
        'house_number',
        'registration_place',
        'religion',
        'previous_school_name',
        'transfer_document_number',
        'transfer_document_date',
        'school_start_date',
        'admitted_class_name',
        'notes',
        'mobile',
        'guardian_mobile',
        'email',
        'school_name',
        'department_name',
        'specialization_name',
        'stage_name',
        'section_name',
        'status',
        'created_at',
        'updated_at',
    ];

    public function findDetail(int $studentId, int $schoolId): ?StudentDetailDTO
    {
        $record = StudentRecord::query()
            ->select(self::DETAIL_COLUMNS)
            ->whereKey($studentId)
            ->where('school_id', $schoolId)
            ->first();

        if ($record === null) {
            return null;
        }

        return $this->mapDetail($record);
    }

    public function paginate(?int $status, int $schoolId, int $page, int $perPage): array
    {
        $query = StudentRecord::query()
            ->select(self::LIST_COLUMNS)
            ->where('school_id', $schoolId)
            ->orderByDesc('id');

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $this->paginateQuery($query, $page, $perPage);
    }

    public function search(string $term, int $schoolId, int $page, int $perPage, ?int $status = null): array
    {
        $term = trim($term);
        $query = StudentRecord::query()
            ->select(self::LIST_COLUMNS)
            ->where('school_id', $schoolId)
            ->orderBy('full_name');

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($term !== '') {
            $likeOperator = SchemaHelper::isPostgreSql() ? 'ilike' : 'like';
            $pattern = '%'.$term.'%';

            $query->where(function (Builder $builder) use ($likeOperator, $pattern): void {
                $builder->where('full_name', $likeOperator, $pattern)
                    ->orWhere('student_code', $likeOperator, $pattern)
                    ->orWhere('national_id', $likeOperator, $pattern)
                    ->orWhere('first_name', $likeOperator, $pattern)
                    ->orWhere('father_name', $likeOperator, $pattern)
                    ->orWhere('grandfather_name', $likeOperator, $pattern)
                    ->orWhere('great_grandfather_name', $likeOperator, $pattern)
                    ->orWhere('last_name', $likeOperator, $pattern)
                    ->orWhere('mother_name', $likeOperator, $pattern)
                    ->orWhere('maternal_father_name', $likeOperator, $pattern)
                    ->orWhere('maternal_grandfather_name', $likeOperator, $pattern);
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

        $paginator = $query->paginate($perPage, self::LIST_COLUMNS, 'page', $page);

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
            firstName: (string) $record->first_name,
            fatherName: $record->father_name,
            grandfatherName: $record->grandfather_name,
            greatGrandfatherName: $record->great_grandfather_name,
            lastName: (string) $record->last_name,
            motherName: $record->mother_name,
            maternalFatherName: $record->maternal_father_name,
            maternalGrandfatherName: $record->maternal_grandfather_name,
            guardianTripleName: $record->guardian_triple_name,
            governorate: $record->governorate,
            neighborhood: $record->neighborhood,
            locality: $record->locality,
            houseNumber: $record->house_number,
            birthDate: $record->birth_date->format('Y-m-d'),
            birthPlace: $record->birth_place,
            registrationPlace: $record->registration_place,
            gender: (int) $record->gender,
            nationality: $record->nationality,
            religion: (int) ($record->religion ?? StudentReligion::Muslim->value),
            mawalidDate: $record->mawalid_date?->format('Y-m-d'),
            nationalId: $record->national_id,
            previousSchoolName: $record->previous_school_name,
            transferDocumentNumber: $record->transfer_document_number !== null
                ? (int) $record->transfer_document_number
                : null,
            transferDocumentDate: $record->transfer_document_date?->format('Y-m-d'),
            schoolStartDate: $record->school_start_date?->format('Y-m-d'),
            admittedClassName: $record->admitted_class_name,
            notes: $record->notes,
            mobile: $record->mobile,
            guardianMobile: $record->guardian_mobile,
            email: $record->email,
            schoolName: $record->school_name,
            departmentName: $record->department_name,
            specializationName: $record->specialization_name,
            stageName: $record->stage_name,
            sectionName: $record->section_name,
            status: (int) $record->status,
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
            fatherName: $record->father_name,
            grandfatherName: $record->grandfather_name,
            greatGrandfatherName: $record->great_grandfather_name,
            lastName: (string) $record->last_name,
            motherName: $record->mother_name,
            maternalFatherName: $record->maternal_father_name,
            maternalGrandfatherName: $record->maternal_grandfather_name,
            fullName: (string) $record->full_name,
            guardianTripleName: $record->guardian_triple_name,
            gender: (int) $record->gender,
            birthDate: $record->birth_date->format('Y-m-d'),
            mawalidDate: $record->mawalid_date?->format('Y-m-d'),
            birthPlace: $record->birth_place,
            nationality: $record->nationality,
            governorate: $record->governorate,
            neighborhood: $record->neighborhood,
            locality: $record->locality,
            houseNumber: $record->house_number,
            registrationPlace: $record->registration_place,
            religion: (int) ($record->religion ?? StudentReligion::Muslim->value),
            previousSchoolName: $record->previous_school_name,
            transferDocumentNumber: $record->transfer_document_number !== null
                ? (int) $record->transfer_document_number
                : null,
            transferDocumentDate: $record->transfer_document_date?->format('Y-m-d'),
            schoolStartDate: $record->school_start_date?->format('Y-m-d'),
            admittedClassName: $record->admitted_class_name,
            notes: $record->notes,
            mobile: $record->mobile,
            guardianMobile: $record->guardian_mobile,
            email: $record->email,
            schoolName: $record->school_name,
            departmentName: $record->department_name,
            specializationName: $record->specialization_name,
            stageName: $record->stage_name,
            sectionName: $record->section_name,
            status: (int) $record->status,
            createdAt: $record->created_at->toIso8601String(),
            updatedAt: $record->updated_at->toIso8601String(),
        );
    }
}
