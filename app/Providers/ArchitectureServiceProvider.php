<?php

namespace App\Providers;

use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Attendance\Contracts\AttendanceReadRepositoryInterface;
use App\Application\Enrollment\Contracts\EnrollmentReadRepositoryInterface;
use App\Application\Exams\Contracts\ExamAdministrationAuthorityPort;
use App\Application\Exams\Contracts\StudentGradeReadRepositoryInterface;
use App\Application\Intelligence\Contracts\DatabaseMonitoringPort;
use App\Application\Intelligence\Contracts\RecommendationCommandPort;
use App\Application\Intelligence\Contracts\RecommendationReadRepositoryInterface;
use App\Application\Observability\Contracts\DatabaseHealthPort;
use App\Application\Observability\Contracts\HttpWorkloadReadRepositoryInterface;
use App\Application\Student\Contracts\StudentReadRepositoryInterface as StudentManagementReadRepositoryInterface;
use App\Domain\Attendance\Repositories\AttendanceWriteRepositoryInterface;
use App\Domain\Enrollment\Repositories\EnrollmentPlacementRepositoryInterface;
use App\Domain\Enrollment\Repositories\EnrollmentRepositoryInterface;
use App\Domain\Enrollment\Repositories\StudentReadRepositoryInterface;
use App\Domain\Academic\Repositories\AcademicYearRepositoryInterface;
use App\Domain\Exams\Repositories\ExamRepositoryInterface;
use App\Infrastructure\Persistence\Academic\EloquentAcademicYearRepository;
use App\Domain\Exams\Repositories\StudentGradeRepositoryInterface;
use App\Domain\Results\Repositories\AnnualResultRepositoryInterface;
use App\Domain\Results\Repositories\GpaResultRepositoryInterface;
use App\Domain\Results\Repositories\RankingSnapshotRepositoryInterface;
use App\Domain\Results\Repositories\TermResultRepositoryInterface;
use App\Domain\Results\Repositories\TranscriptRepositoryInterface;
use App\Domain\Timetable\Repositories\ScheduleExceptionRepositoryInterface;
use App\Domain\Timetable\Repositories\ScheduleRepositoryInterface;
use App\Domain\Vocational\Repositories\VocationalCatalogRepositoryInterface;
use App\Infrastructure\Persistence\Results\EloquentAnnualResultRepository;
use App\Infrastructure\Persistence\Results\EloquentGpaResultRepository;
use App\Infrastructure\Persistence\Results\EloquentRankingSnapshotRepository;
use App\Infrastructure\Persistence\Results\EloquentTermResultRepository;
use App\Infrastructure\Persistence\Results\EloquentTranscriptRepository;
use App\Infrastructure\Persistence\Timetable\EloquentScheduleExceptionRepository;
use App\Infrastructure\Persistence\Timetable\EloquentScheduleRepository;
use App\Infrastructure\Persistence\Vocational\EloquentVocationalCatalogRepository;
use App\Application\Graduation\Contracts\GraduationAuthorityPort;
use App\Application\Graduation\Contracts\GraduationReadRepositoryInterface;
use App\Domain\Graduation\Repositories\GraduationWriteRepositoryInterface;
use App\Domain\Student\Repositories\StudentRepositoryInterface;
use App\Infrastructure\Events\EnrollmentCancelledBridgeEvent;
use App\Infrastructure\Exams\PermissionCatalogExamAuthority;
use App\Infrastructure\Graduation\FailClosedGraduationAuthority;
use App\Infrastructure\Persistence\Graduation\EloquentGraduationReadRepository;
use App\Infrastructure\Persistence\Graduation\EloquentGraduationWriteRepository;
use App\Infrastructure\Events\EnrollmentPlacementUpdatedBridgeEvent;
use App\Infrastructure\Events\StudentEnrolledBridgeEvent;
use App\Infrastructure\Intelligence\PgStatDatabaseMonitoringAdapter;
use App\Infrastructure\Intelligence\RecommendationCommandAdapter;
use App\Infrastructure\Observability\EloquentDatabaseHealthAdapter;
use App\Infrastructure\Observability\EloquentHttpWorkloadReadRepository;
use App\Infrastructure\Persistence\EloquentUnitOfWork;
use App\Infrastructure\Persistence\Attendance\EloquentAttendanceReadRepository;
use App\Infrastructure\Persistence\Attendance\EloquentAttendanceWriteRepository;
use App\Infrastructure\Persistence\Enrollment\EloquentEnrollmentPlacementRepository;
use App\Infrastructure\Persistence\Enrollment\EloquentEnrollmentReadRepository;
use App\Infrastructure\Persistence\Enrollment\EloquentEnrollmentRepository;
use App\Infrastructure\Persistence\Exams\EloquentExamRepository;
use App\Infrastructure\Persistence\Exams\EloquentStudentGradeReadRepository;
use App\Infrastructure\Persistence\Exams\EloquentStudentGradeRepository;
use App\Infrastructure\Persistence\Idempotency\EloquentIdempotencyStore;
use App\Infrastructure\Persistence\Intelligence\EloquentRecommendationReadRepository;
use App\Infrastructure\Persistence\Outbox\EloquentOutboxRepository;
use App\Infrastructure\Persistence\Student\EloquentStudentManagementReadRepository;
use App\Infrastructure\Persistence\Student\EloquentStudentReadRepository;
use App\Infrastructure\Persistence\Student\EloquentStudentRepository;
use App\Listeners\Enrollment\RecordEnrollmentCancelledAudit;
use App\Listeners\Enrollment\RecordEnrollmentPlacementUpdatedAudit;
use App\Listeners\Enrollment\RecordStudentEnrolledAudit;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class ArchitectureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(UnitOfWork::class, EloquentUnitOfWork::class);
        $this->app->singleton(OutboxRepository::class, EloquentOutboxRepository::class);
        $this->app->singleton(IdempotencyStore::class, EloquentIdempotencyStore::class);

        $this->app->bind(EnrollmentRepositoryInterface::class, EloquentEnrollmentRepository::class);
        $this->app->bind(EnrollmentPlacementRepositoryInterface::class, EloquentEnrollmentPlacementRepository::class);
        $this->app->bind(EnrollmentReadRepositoryInterface::class, EloquentEnrollmentReadRepository::class);
        $this->app->bind(StudentGradeRepositoryInterface::class, EloquentStudentGradeRepository::class);
        $this->app->bind(StudentGradeReadRepositoryInterface::class, EloquentStudentGradeReadRepository::class);
        $this->app->bind(TermResultRepositoryInterface::class, EloquentTermResultRepository::class);
        $this->app->bind(AnnualResultRepositoryInterface::class, EloquentAnnualResultRepository::class);
        $this->app->bind(GpaResultRepositoryInterface::class, EloquentGpaResultRepository::class);
        $this->app->bind(RankingSnapshotRepositoryInterface::class, EloquentRankingSnapshotRepository::class);
        $this->app->bind(TranscriptRepositoryInterface::class, EloquentTranscriptRepository::class);
        $this->app->bind(ScheduleRepositoryInterface::class, EloquentScheduleRepository::class);
        $this->app->bind(ScheduleExceptionRepositoryInterface::class, EloquentScheduleExceptionRepository::class);
        $this->app->bind(VocationalCatalogRepositoryInterface::class, EloquentVocationalCatalogRepository::class);
        $this->app->bind(AcademicYearRepositoryInterface::class, EloquentAcademicYearRepository::class);
        $this->app->bind(ExamRepositoryInterface::class, EloquentExamRepository::class);
        $this->app->bind(ExamAdministrationAuthorityPort::class, PermissionCatalogExamAuthority::class);
        $this->app->bind(StudentRepositoryInterface::class, EloquentStudentRepository::class);
        $this->app->bind(StudentReadRepositoryInterface::class, EloquentStudentReadRepository::class);
        $this->app->bind(StudentManagementReadRepositoryInterface::class, EloquentStudentManagementReadRepository::class);
        $this->app->bind(HttpWorkloadReadRepositoryInterface::class, EloquentHttpWorkloadReadRepository::class);
        $this->app->bind(DatabaseHealthPort::class, EloquentDatabaseHealthAdapter::class);
        $this->app->bind(RecommendationReadRepositoryInterface::class, EloquentRecommendationReadRepository::class);
        $this->app->bind(RecommendationCommandPort::class, RecommendationCommandAdapter::class);
        $this->app->bind(DatabaseMonitoringPort::class, PgStatDatabaseMonitoringAdapter::class);
        $this->app->bind(GraduationWriteRepositoryInterface::class, EloquentGraduationWriteRepository::class);
        $this->app->bind(GraduationReadRepositoryInterface::class, EloquentGraduationReadRepository::class);
        $this->app->bind(GraduationAuthorityPort::class, FailClosedGraduationAuthority::class);
        $this->app->bind(AttendanceWriteRepositoryInterface::class, EloquentAttendanceWriteRepository::class);
        $this->app->bind(AttendanceReadRepositoryInterface::class, EloquentAttendanceReadRepository::class);
    }

    public function boot(): void
    {
        Event::listen(StudentEnrolledBridgeEvent::class, RecordStudentEnrolledAudit::class);
        Event::listen(EnrollmentCancelledBridgeEvent::class, RecordEnrollmentCancelledAudit::class);
        Event::listen(EnrollmentPlacementUpdatedBridgeEvent::class, RecordEnrollmentPlacementUpdatedAudit::class);
    }
}
