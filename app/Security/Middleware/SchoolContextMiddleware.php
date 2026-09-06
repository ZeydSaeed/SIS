<?php

namespace App\Security\Middleware;

use App\Database\SchemaHelper;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets PostgreSQL session variable for RLS policies on enrollment + attendance.
 */
class SchoolContextMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (SchemaHelper::isPostgreSql() && $request->user() !== null) {
            $schoolId = $this->resolveSchoolId($request);

            if ($schoolId !== null) {
                DB::statement("SELECT set_config('app.current_school_id', ?, false)", [(string) $schoolId]);
            }
        }

        return $next($request);
    }

    private function resolveSchoolId(Request $request): ?int
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        if (isset($user->school_id)) {
            return (int) $user->school_id;
        }

        $fromSession = $request->session()->get('current_school_id');
        if (is_numeric($fromSession)) {
            return (int) $fromSession;
        }

        $fromHeader = $request->header('X-School-Id');
        if (is_numeric($fromHeader)) {
            return (int) $fromHeader;
        }

        return null;
    }
}
