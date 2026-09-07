<?php

namespace App\Security\Context;

use App\Models\User;
use App\Security\Authorization\SchoolScopeService;
use Illuminate\Http\Request;

final class SchoolContextResolver
{
    public function __construct(
        private readonly SchoolScopeService $schoolScope,
    ) {}

    public function resolve(Request $request): ?int
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }

        $allowed = $this->schoolScope->allowedSchoolIds($user);
        if ($allowed === []) {
            return null;
        }

        $fromHeader = $request->header('X-School-Id');
        if (is_numeric($fromHeader)) {
            $candidate = (int) $fromHeader;

            return in_array($candidate, $allowed, true) ? $candidate : null;
        }

        if ($request->hasSession()) {
            $fromSession = $request->session()->get('current_school_id');
            if (is_numeric($fromSession)) {
                $candidate = (int) $fromSession;

                return in_array($candidate, $allowed, true) ? $candidate : null;
            }
        }

        if (count($allowed) === 1 && config('security.allow_implicit_single_school', false)) {
            return $allowed[0];
        }

        return null;
    }

    public function userCanAccessSchool(User $user, int $schoolId): bool
    {
        return in_array($schoolId, $this->schoolScope->allowedSchoolIds($user), true);
    }
}
