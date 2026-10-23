<?php

namespace App\Http\Support;

use App\Application\Enrollment\DTOs\EnrollmentDTO;
use App\Application\Enrollment\Queries\GetEnrollmentHandler;
use App\Application\Enrollment\Queries\GetEnrollmentQuery;
use App\Application\Enrollment\Queries\ListEnrollmentsHandler;
use App\Application\Enrollment\Queries\ListEnrollmentsQuery;
use Illuminate\Http\Request;

/**
 * «اختيار الطالب» on the results pages: search the year's enrollments by name or code (`q`, at most 20 matches) and
 * describe the chosen one — so nobody types a raw enrollment id. School-scoped by the enrollment queries.
 */
final class ResultsStudentPicker
{
    private const LIMIT = 20;

    /** No search and no student chosen yet: the first students of the year, to choose from without typing. */
    private const BROWSE = 50;

    public function __construct(
        private readonly ListEnrollmentsHandler $list,
        private readonly GetEnrollmentHandler $get,
    ) {}

    /**
     * @return array{q: string, candidates: list<array{enrollment_id: int, student_name: string, student_code: string, placement: string}>,
     *     student: array{enrollment_id: int, student_name: string, student_code: string, placement: string}|null}
     */
    public function props(Request $request, int $schoolId, ?int $academicYearId, ?int $enrollmentId): array
    {
        $q = trim((string) $request->query('q', ''));
        $candidates = [];
        $browse = $q === '' && ($enrollmentId === null || $enrollmentId <= 0);
        if (($q !== '' || $browse) && $academicYearId !== null) {
            $page = $this->list->handle(new ListEnrollmentsQuery(schoolId: $schoolId, academicYearId: $academicYearId, perPage: $browse ? self::BROWSE : self::LIMIT, q: $q));
            $candidates = array_map(fn (EnrollmentDTO $dto): array => $this->describe($dto), $page->items);
        }

        $student = $enrollmentId !== null && $enrollmentId > 0
            ? $this->describe($this->get->handle(new GetEnrollmentQuery($enrollmentId, $schoolId)))
            : null;

        return ['q' => $q, 'candidates' => $candidates, 'student' => $student];
    }

    /** @return array{enrollment_id: int, student_name: string, student_code: string, placement: string} */
    private function describe(EnrollmentDTO $dto): array
    {
        return [
            'enrollment_id' => $dto->id,
            'student_name' => (string) $dto->studentFullName,
            'student_code' => (string) $dto->studentCode,
            'placement' => trim(($dto->className ?? '').' / '.($dto->sectionName ?? ''), ' /'),
        ];
    }
}
