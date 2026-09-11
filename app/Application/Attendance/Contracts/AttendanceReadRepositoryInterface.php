<?php

namespace App\Application\Attendance\Contracts;

use App\Application\Attendance\DTOs\AttendanceRecordDTO;
use App\Application\Attendance\DTOs\AttendanceSessionDTO;
use App\Application\Attendance\DTOs\DailySectionSummaryDTO;
use App\Application\Attendance\DTOs\SectionAttendanceDTO;

interface AttendanceReadRepositoryInterface
{
    public function getSession(int $schoolId, int $sessionId, bool $includeRecords = false): ?AttendanceSessionDTO;

    /**
     * @return array{items: list<AttendanceSessionDTO>, pagination: array<string, int>}
     */
    public function listSessions(
        int $schoolId,
        int $academicYearId,
        ?int $sectionId,
        ?string $dateFrom,
        ?string $dateTo,
        ?int $status,
        int $page,
        int $perPage,
    ): array;

    public function getSectionAttendance(
        int $schoolId,
        int $sectionId,
        string $date,
        int $academicYearId,
    ): ?SectionAttendanceDTO;

    /**
     * @return array{items: list<AttendanceRecordDTO>, pagination: array<string, int>}
     */
    public function getStudentAttendance(
        int $schoolId,
        int $studentId,
        int $academicYearId,
        ?string $dateFrom,
        ?string $dateTo,
        int $page,
        int $perPage,
    ): array;

    /**
     * @return list<DailySectionSummaryDTO>
     */
    public function getDailySectionSummary(
        int $schoolId,
        int $sectionId,
        ?string $date,
        ?string $dateFrom,
        ?string $dateTo,
    ): array;
}
