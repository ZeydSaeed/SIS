<?php

namespace App\Infrastructure\Attendance;

use App\Application\Attendance\Contracts\TeacherDirectoryInterface;
use Illuminate\Support\Facades\DB;

final class EloquentTeacherDirectory implements TeacherDirectoryInterface
{
    public function teacherIdForUser(int $userId, int $schoolId, int $academicYearId): ?int
    {
        $id = DB::table('teachers.teachers as t')
            ->join('teachers.teacher_schools as ts', 'ts.teacher_id', '=', 't.id')
            ->where('t.user_id', $userId)
            ->where('t.status', 1)
            ->where('ts.school_id', $schoolId)
            ->where('ts.academic_year_id', $academicYearId)
            ->whereNull('ts.left_at')
            ->orderBy('t.id')
            ->value('t.id');

        return $id !== null ? (int) $id : null;
    }

    public function teacher(int $teacherId): ?array
    {
        $row = DB::table('teachers.teachers')->where('id', $teacherId)->first(['id', 'full_name', 'employee_code']);

        return $row === null ? null : ['id' => (int) $row->id, 'full_name' => (string) $row->full_name, 'employee_code' => (string) $row->employee_code];
    }
}
