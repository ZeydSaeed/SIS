<?php

namespace App\Infrastructure\Persistence\Portal;

use App\Database\SchemaHelper;
use App\Domain\Portal\Data\PortalScopeSnapshot;
use App\Domain\Portal\Repositories\PortalScopeRepositoryInterface;
use App\Domain\Portal\ValueObjects\PortalScopeType;
use Illuminate\Support\Facades\DB;

final class EloquentPortalScopeRepository implements PortalScopeRepositoryInterface
{
    public function userExists(int $userId): bool
    {
        return DB::table('users')->where('id', $userId)->exists();
    }

    public function studentExists(int $studentId): bool
    {
        return DB::table(SchemaHelper::qualified('students', 'students'))
            ->where('id', $studentId)
            ->exists();
    }

    public function guardianExists(int $guardianId): bool
    {
        return DB::table(SchemaHelper::qualified('guardians', 'guardians'))
            ->where('id', $guardianId)
            ->exists();
    }

    public function studentHasEnrollmentInSchool(int $studentId, int $schoolId): bool
    {
        return DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))
            ->where('student_id', $studentId)
            ->where('school_id', $schoolId)
            ->exists();
    }

    public function guardianLinkedToStudentInSchool(int $guardianId, int $schoolId): bool
    {
        $links = SchemaHelper::qualified('guardians', 'student_guardians');
        $enrollments = SchemaHelper::qualified('enrollment', 'enrollments');

        return DB::table($links.' as sg')
            ->join($enrollments.' as e', 'e.student_id', '=', 'sg.student_id')
            ->where('sg.guardian_id', $guardianId)
            ->where('e.school_id', $schoolId)
            ->exists();
    }

    public function findScopeId(int $userId, string $scopeType, int $scopeId): ?int
    {
        $id = DB::table(SchemaHelper::qualified('security', 'scopes'))
            ->where('user_id', $userId)
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    public function insertScope(int $userId, string $scopeType, int $scopeId, string $createdAt): int
    {
        return (int) DB::table(SchemaHelper::qualified('security', 'scopes'))->insertGetId([
            'user_id' => $userId,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'created_at' => $createdAt,
        ]);
    }

    public function deleteScope(int $userId, string $scopeType, int $scopeId): bool
    {
        return DB::table(SchemaHelper::qualified('security', 'scopes'))
            ->where('user_id', $userId)
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->delete() > 0;
    }

    public function listForUser(int $userId): array
    {
        return DB::table(SchemaHelper::qualified('security', 'scopes'))
            ->where('user_id', $userId)
            ->whereIn('scope_type', [PortalScopeType::STUDENT, PortalScopeType::GUARDIAN])
            ->orderBy('id')
            ->get(['id', 'user_id', 'scope_type', 'scope_id', 'created_at'])
            ->map(static fn ($row): PortalScopeSnapshot => new PortalScopeSnapshot(
                id: (int) $row->id,
                userId: (int) $row->user_id,
                scopeType: (string) $row->scope_type,
                scopeId: (int) $row->scope_id,
                createdAt: (string) $row->created_at,
            ))
            ->all();
    }
}
