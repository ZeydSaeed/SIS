<?php

namespace App\Security\Authorization;

use App\Database\SchemaHelper;
use App\Models\User;
use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use Illuminate\Support\Facades\DB;

/**
 * Resolves portal party ownership from security.scopes + guardians.student_guardians.
 *
 * Access is evaluated per target student (not “load all then contains”),
 * so guardian/student deny paths cannot widen accidentally.
 */
final class PortalPartyAccessService
{
    public function __construct(
        private readonly ResultsSchoolAccessService $schoolAccess,
        private readonly AuthorizationServiceInterface $authorization,
    ) {}

    public function canViewPortal(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::PORTAL_RESULTS_VIEW)
            && $this->schoolAccess->canAccessResults($user);
    }

    public function canAccessEnrollment(User $user, int $enrollmentId, int $schoolId): bool
    {
        if (! $this->canViewPortal($user)) {
            return false;
        }

        if (! $this->schoolAccess->canAccessResults($user, $schoolId)) {
            return false;
        }

        $studentId = $this->resolveEnrollmentStudentId($enrollmentId, $schoolId);
        if ($studentId === null) {
            return false;
        }

        return $this->ownsStudent($user, $studentId);
    }

    /**
     * Direct student scope OR verified guardian→student relationship.
     */
    public function ownsStudent(User $user, int $studentId): bool
    {
        if ($this->hasDirectStudentScope($user, $studentId)) {
            return true;
        }

        return $this->hasGuardianRelationship($user, $studentId);
    }

    /**
     * @return list<int>
     */
    public function allowedStudentIds(User $user): array
    {
        $scopesTable = SchemaHelper::qualified('security', 'scopes');
        $linksTable = SchemaHelper::qualified('guardians', 'student_guardians');

        $direct = DB::table($scopesTable)
            ->where('user_id', $user->id)
            ->where('scope_type', PortalScopeType::STUDENT)
            ->pluck('scope_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $guardianIds = DB::table($scopesTable)
            ->where('user_id', $user->id)
            ->where('scope_type', PortalScopeType::GUARDIAN)
            ->pluck('scope_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $viaGuardian = [];
        if ($guardianIds !== []) {
            $viaGuardian = DB::table($linksTable)
                ->whereIn('guardian_id', $guardianIds)
                ->pluck('student_id')
                ->map(static fn ($id): int => (int) $id)
                ->all();
        }

        return array_values(array_unique(array_merge($direct, $viaGuardian)));
    }

    private function hasDirectStudentScope(User $user, int $studentId): bool
    {
        return DB::table(SchemaHelper::qualified('security', 'scopes'))
            ->where('user_id', $user->id)
            ->where('scope_type', PortalScopeType::STUDENT)
            ->where('scope_id', $studentId)
            ->exists();
    }

    private function hasGuardianRelationship(User $user, int $studentId): bool
    {
        $scopesTable = SchemaHelper::qualified('security', 'scopes');
        $linksTable = SchemaHelper::qualified('guardians', 'student_guardians');
        $guardiansTable = SchemaHelper::qualified('guardians', 'guardians');

        $guardianIds = DB::table($scopesTable)
            ->where('user_id', $user->id)
            ->where('scope_type', PortalScopeType::GUARDIAN)
            ->pluck('scope_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($guardianIds === []) {
            return false;
        }

        // Scope must point at a real guardian row (ignore dangling scope_id).
        $validGuardianIds = DB::table($guardiansTable)
            ->whereIn('id', $guardianIds)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($validGuardianIds === []) {
            return false;
        }

        return DB::table($linksTable)
            ->whereIn('guardian_id', $validGuardianIds)
            ->where('student_id', $studentId)
            ->exists();
    }

    private function resolveEnrollmentStudentId(int $enrollmentId, int $schoolId): ?int
    {
        $row = DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))
            ->where('id', $enrollmentId)
            ->where('school_id', $schoolId)
            ->first(['student_id']);

        if ($row === null) {
            return null;
        }

        return (int) $row->student_id;
    }
}
