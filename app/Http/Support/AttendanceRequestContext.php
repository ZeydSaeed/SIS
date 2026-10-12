<?php

namespace App\Http\Support;

use App\Application\Attendance\Contracts\TeacherDirectoryInterface;
use App\Application\Attendance\Support\SheetActor;
use App\Infrastructure\Persistence\Eloquent\AttendanceSessionRecord;
use App\Models\User;
use App\Security\Context\SchoolContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Resolves the tenant scope and the acting user for attendance requests from the session and the authenticated
 * user only — never from request fields (no client-supplied school, teacher or reviewer flag).
 */
final class AttendanceRequestContext
{
    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly AcademicYearContextResolver $years,
        private readonly TeacherDirectoryInterface $teachers,
    ) {}

    public function schoolId(): int
    {
        return $this->schoolContext->requireId();
    }

    public function academicYearId(Request $request): int
    {
        $requested = $request->filled('academic_year_id') ? (int) $request->query('academic_year_id') : null;
        $yearId = $this->years->resolve($requested, $request);
        if ($yearId === null) {
            throw new HttpException(409, 'No academic year is configured.');
        }

        return $yearId;
    }

    public function actor(Request $request, int $academicYearId): SheetActor
    {
        $user = $request->user();
        assert($user instanceof User);

        return new SheetActor(
            (int) $user->id,
            $this->teachers->teacherIdForUser((int) $user->id, $this->schoolId(), $academicYearId),
            $user->can('reviewSheet', AttendanceSessionRecord::class),
        );
    }

    /**
     * @return array<string, bool>
     */
    public function abilities(Request $request): array
    {
        $user = $request->user();
        assert($user instanceof User);
        $can = static fn (string $ability): bool => $user->can($ability, AttendanceSessionRecord::class);

        return [
            'submit_sheet' => $can('submitSheet'),
            'review_sheet' => $can('reviewSheet'),
            'correct' => $can('correct'),
            'submit_excuse' => $can('submitExcuse'),
            'review_excuse' => $can('reviewExcuse'),
            'record_staff' => $can('recordStaff'),
            'staff_checkin' => $can('staffCheckIn'),
            'view_reports' => $can('viewReports'),
            'manage_alerts' => $can('manageAlerts'),
            'manage_policy' => $can('managePolicy'),
            'link_account' => $can('linkTeacherAccount'),
        ];
    }
}
