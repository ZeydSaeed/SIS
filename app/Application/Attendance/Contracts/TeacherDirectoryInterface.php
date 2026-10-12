<?php

namespace App\Application\Attendance\Contracts;

interface TeacherDirectoryInterface
{
    /** The teacher linked to the user account (teachers.teachers.user_id) who works in the school that year. */
    public function teacherIdForUser(int $userId, int $schoolId, int $academicYearId): ?int;

    /**
     * @return array{id: int, full_name: string, employee_code: string}|null
     */
    public function teacher(int $teacherId): ?array;
}
