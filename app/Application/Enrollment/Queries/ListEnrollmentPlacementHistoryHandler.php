<?php

namespace App\Application\Enrollment\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Enrollment\Contracts\EnrollmentReadRepositoryInterface;
use App\Application\Enrollment\DTOs\EnrollmentDTO;

final class ListEnrollmentPlacementHistoryHandler implements QueryHandler
{
    public function __construct(
        private readonly EnrollmentReadRepositoryInterface $enrollments,
    ) {}

    /**
     * Placement timelines keyed in the same order as requested student IDs.
     *
     * @return list<array{
     *     student_id: int,
     *     student_full_name: ?string,
     *     student_code: ?string,
     *     academic_year_id: ?int,
     *     academic_year_name: ?string,
     *     academic_year_code: ?string,
     *     segments: list<array<string, mixed>>
     * }>
     */
    public function handle(Query $query): array
    {
        assert($query instanceof ListEnrollmentPlacementHistoryQuery);

        $studentIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $query->studentIds),
            static fn (int $id): bool => $id > 0,
        )));

        if ($studentIds === []) {
            return [];
        }

        /** @var list<EnrollmentDTO> $segments */
        $segments = $this->enrollments->listPlacementHistory(
            $query->schoolId,
            $studentIds,
            $query->academicYearId,
        );

        /** @var array<int, list<EnrollmentDTO>> $byStudent */
        $byStudent = [];
        foreach ($segments as $segment) {
            $byStudent[$segment->studentId][] = $segment;
        }

        $items = [];
        foreach ($studentIds as $studentId) {
            $studentSegments = $byStudent[$studentId] ?? [];
            $first = $studentSegments[0] ?? null;
            $items[] = [
                'student_id' => $studentId,
                'student_full_name' => $first?->studentFullName,
                'student_code' => $first?->studentCode,
                'academic_year_id' => $query->academicYearId,
                'academic_year_name' => $first?->academicYearName,
                'academic_year_code' => $first?->academicYearCode,
                'segments' => array_map(
                    static fn (EnrollmentDTO $segment): array => $segment->toArray(),
                    $studentSegments,
                ),
            ];
        }

        return $items;
    }
}
