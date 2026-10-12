<?php

namespace App\Infrastructure\Persistence\Teachers;

use App\Domain\Teachers\Repositories\TeacherAccountRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class EloquentTeacherAccountRepository implements TeacherAccountRepositoryInterface
{
    public function teacherWorksInSchool(int $teacherId, int $schoolId, int $academicYearId): bool
    {
        return DB::table('teachers.teacher_schools')
            ->where('teacher_id', $teacherId)->where('school_id', $schoolId)->where('academic_year_id', $academicYearId)
            ->whereNull('left_at')
            ->exists();
    }

    public function userIdByEmail(string $email): ?int
    {
        $id = DB::table('users')->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])->value('id');

        return $id !== null ? (int) $id : null;
    }

    public function teacherLinkedToUser(int $userId, int $exceptTeacherId): ?int
    {
        $id = DB::table('teachers.teachers')->where('user_id', $userId)->where('id', '<>', $exceptTeacherId)->value('id');

        return $id !== null ? (int) $id : null;
    }

    public function currentUserId(int $teacherId): ?int
    {
        $id = DB::table('teachers.teachers')->where('id', $teacherId)->lockForUpdate()->value('user_id');

        return $id !== null ? (int) $id : null;
    }

    public function link(int $teacherId, ?int $userId): void
    {
        DB::table('teachers.teachers')->where('id', $teacherId)->update(['user_id' => $userId, 'updated_at' => now()]);
    }
}
