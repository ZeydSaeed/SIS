<?php

namespace App\Providers;

use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Intelligence\Contracts\DatabaseMonitoringPort;
use App\Application\Intelligence\Contracts\RecommendationCommandPort;
use App\Application\Intelligence\Contracts\RecommendationReadRepositoryInterface;
use App\Application\Observability\Contracts\DatabaseHealthPort;
use App\Application\Observability\Contracts\HttpWorkloadReadRepositoryInterface;
use App\Application\Student\Contracts\StudentReadRepositoryInterface as StudentManagementReadRepositoryInterface;
use App\Domain\Enrollment\Repositories\EnrollmentRepositoryInterface;
use App\Domain\Enrollment\Repositories\StudentReadRepositoryInterface;
use App\Domain\Student\Repositories\StudentRepositoryInterface;
use App\Infrastructure\Events\StudentEnrolledBridgeEvent;
use App\Infrastructure\Intelligence\PgStatDatabaseMonitoringAdapter;
use App\Infrastructure\Intelligence\RecommendationCommandAdapter;
use App\Infrastructure\Observability\EloquentDatabaseHealthAdapter;
use App\Infrastructure\Observability\EloquentHttpWorkloadReadRepository;
use App\Infrastructure\Persistence\EloquentUnitOfWork;
use App\Infrastructure\Persistence\Enrollment\EloquentEnrollmentRepository;
use App\Infrastructure\Persistence\Idempotency\EloquentIdempotencyStore;
use App\Infrastructure\Persistence\Intelligence\EloquentRecommendationReadRepository;
use App\Infrastructure\Persistence\Outbox\EloquentOutboxRepository;
use App\Infrastructure\Persistence\Student\EloquentStudentManagementReadRepository;
use App\Infrastructure\Persistence\Student\EloquentStudentReadRepository;
use App\Infrastructure\Persistence\Student\EloquentStudentRepository;
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
        $this->app->bind(StudentRepositoryInterface::class, EloquentStudentRepository::class);
        $this->app->bind(StudentReadRepositoryInterface::class, EloquentStudentReadRepository::class);
        $this->app->bind(StudentManagementReadRepositoryInterface::class, EloquentStudentManagementReadRepository::class);
        $this->app->bind(HttpWorkloadReadRepositoryInterface::class, EloquentHttpWorkloadReadRepository::class);
        $this->app->bind(DatabaseHealthPort::class, EloquentDatabaseHealthAdapter::class);
        $this->app->bind(RecommendationReadRepositoryInterface::class, EloquentRecommendationReadRepository::class);
        $this->app->bind(RecommendationCommandPort::class, RecommendationCommandAdapter::class);
        $this->app->bind(DatabaseMonitoringPort::class, PgStatDatabaseMonitoringAdapter::class);
    }

    public function boot(): void
    {
        Event::listen(StudentEnrolledBridgeEvent::class, RecordStudentEnrolledAudit::class);
    }
}
