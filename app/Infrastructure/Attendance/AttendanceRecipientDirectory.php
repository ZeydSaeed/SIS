<?php

namespace App\Infrastructure\Attendance;

use Illuminate\Support\Facades\DB;

/** Who should be told about an attendance workflow outcome (users only — resolved server-side, school-scoped). */
final class AttendanceRecipientDirectory
{
    private const CAP = 50;

    /**
     * Users holding a permission through a role that is active today for this school (or for all schools).
     *
     * @return list<int>
     */
    public function usersWithPermission(int $schoolId, string $permission): array
    {
        $today = now()->toDateString();

        return DB::table('security.user_roles as ur')
            ->join('security.role_permissions as rp', 'rp.role_id', '=', 'ur.role_id')
            ->join('security.permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('p.code', $permission)
            ->where(static fn ($q) => $q->where('ur.school_id', $schoolId)->orWhereNull('ur.school_id'))
            ->where(static fn ($q) => $q->whereNull('ur.effective_from')->orWhere('ur.effective_from', '<=', $today))
            ->where(static fn ($q) => $q->whereNull('ur.effective_to')->orWhere('ur.effective_to', '>=', $today))
            ->distinct()
            ->limit(self::CAP)
            ->pluck('ur.user_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    public function userOfTeacher(int $teacherId): ?int
    {
        $id = DB::table('teachers.teachers')->where('id', $teacherId)->value('user_id');

        return $id !== null ? (int) $id : null;
    }

    public function submitterOfSession(int $sessionId): ?int
    {
        $id = DB::table('attendance.sessions')->where('id', $sessionId)->value('submitted_by');

        return $id !== null ? (int) $id : null;
    }
}
