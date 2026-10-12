<?php

namespace App\Providers;

use App\Application\Attendance\Contracts\AttendanceAnalyticsReadInterface;
use App\Application\Attendance\Contracts\AttendanceClockInterface;
use App\Application\Attendance\Contracts\AttendanceReportReadInterface;
use App\Application\Attendance\Contracts\AttendanceWorkspaceReadInterface;
use App\Application\Attendance\Contracts\CheckinCodeHasherInterface;
use App\Application\Attendance\Contracts\CheckinQrRendererInterface;
use App\Application\Attendance\Contracts\ExcuseDocumentStorageInterface;
use App\Application\Attendance\Contracts\LessonSourceInterface;
use App\Application\Attendance\Contracts\ReportExportDispatcherInterface;
use App\Application\Attendance\Contracts\SchoolScopedRunnerInterface;
use App\Application\Attendance\Contracts\TeacherDirectoryInterface;
use App\Application\Attendance\Support\AttendancePolicyResolver;
use App\Domain\Attendance\Repositories\AttendanceAlertRepositoryInterface;
use App\Domain\Attendance\Repositories\AttendanceExcuseRepositoryInterface;
use App\Domain\Attendance\Repositories\AttendancePolicyRepositoryInterface;
use App\Domain\Attendance\Repositories\AttendanceReportExportRepositoryInterface;
use App\Domain\Attendance\Repositories\AttendanceSheetRepositoryInterface;
use App\Domain\Attendance\Repositories\StaffAttendanceRepositoryInterface;
use App\Domain\Teachers\Repositories\TeacherAccountRepositoryInterface;
use App\Infrastructure\Attendance\BaconCheckinQrRenderer;
use App\Infrastructure\Attendance\DocumentsExcuseDocumentStorage;
use App\Infrastructure\Attendance\EloquentTeacherDirectory;
use App\Infrastructure\Attendance\HmacCheckinCodeHasher;
use App\Infrastructure\Attendance\QueueReportExportDispatcher;
use App\Infrastructure\Attendance\SchoolContextScopeRunner;
use App\Infrastructure\Attendance\SystemAttendanceClock;
use App\Infrastructure\Attendance\TimetableLessonSource;
use App\Infrastructure\Events\AttendanceWorkflowBridgeEvent;
use App\Infrastructure\Persistence\Attendance\EloquentAttendanceAlertRepository;
use App\Infrastructure\Persistence\Attendance\EloquentAttendanceExcuseRepository;
use App\Infrastructure\Persistence\Attendance\EloquentAttendancePolicyRepository;
use App\Infrastructure\Persistence\Attendance\EloquentAttendanceReportExportRepository;
use App\Infrastructure\Persistence\Attendance\EloquentAttendanceReportRead;
use App\Infrastructure\Persistence\Attendance\EloquentAttendanceSheetRepository;
use App\Infrastructure\Persistence\Attendance\EloquentAttendanceWorkspaceRead;
use App\Infrastructure\Persistence\Attendance\EloquentStaffAttendanceRepository;
use App\Infrastructure\Persistence\Teachers\EloquentTeacherAccountRepository;
use App\Listeners\Attendance\RecordAttendanceWorkflowOutcome;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/** Attendance & Absence Management — ports → adapters, and the outbox listener. */
final class AttendanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AttendanceSheetRepositoryInterface::class, EloquentAttendanceSheetRepository::class);
        $this->app->bind(AttendancePolicyRepositoryInterface::class, EloquentAttendancePolicyRepository::class);
        $this->app->bind(AttendanceExcuseRepositoryInterface::class, EloquentAttendanceExcuseRepository::class);
        $this->app->bind(StaffAttendanceRepositoryInterface::class, EloquentStaffAttendanceRepository::class);
        $this->app->bind(AttendanceAlertRepositoryInterface::class, EloquentAttendanceAlertRepository::class);
        $this->app->bind(AttendanceWorkspaceReadInterface::class, EloquentAttendanceWorkspaceRead::class);
        $this->app->bind(AttendanceReportReadInterface::class, EloquentAttendanceReportRead::class);
        $this->app->bind(AttendanceAnalyticsReadInterface::class, EloquentAttendanceReportRead::class);
        $this->app->bind(LessonSourceInterface::class, TimetableLessonSource::class);
        $this->app->bind(TeacherDirectoryInterface::class, EloquentTeacherDirectory::class);
        $this->app->bind(SchoolScopedRunnerInterface::class, SchoolContextScopeRunner::class);
        $this->app->bind(AttendanceClockInterface::class, SystemAttendanceClock::class);
        $this->app->bind(CheckinQrRendererInterface::class, BaconCheckinQrRenderer::class);
        $this->app->bind(ExcuseDocumentStorageInterface::class, DocumentsExcuseDocumentStorage::class);
        $this->app->bind(AttendanceReportExportRepositoryInterface::class, EloquentAttendanceReportExportRepository::class);
        $this->app->bind(ReportExportDispatcherInterface::class, QueueReportExportDispatcher::class);
        // Teacher ↔ user account link (Teachers context; used by attendance to recognise teachers).
        $this->app->bind(TeacherAccountRepositoryInterface::class, EloquentTeacherAccountRepository::class);
        $this->app->bind(CheckinCodeHasherInterface::class, fn (): HmacCheckinCodeHasher => new HmacCheckinCodeHasher((string) config('app.key')));
        $this->app->bind(AttendancePolicyResolver::class, fn ($app): AttendancePolicyResolver => new AttendancePolicyResolver(
            $app->make(AttendancePolicyRepositoryInterface::class),
            (string) config('attendance.default_timezone', 'UTC'),
        ));
    }

    public function boot(): void
    {
        Event::listen(AttendanceWorkflowBridgeEvent::class, RecordAttendanceWorkflowOutcome::class);
    }
}
