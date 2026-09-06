<?php

namespace App\Providers;

use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Intelligence\Contracts\DatabaseMonitoringPort;
use App\Application\Intelligence\Contracts\RecommendationCommandPort;
use App\Application\Intelligence\Contracts\RecommendationReadRepositoryInterface;
use App\Domain\Enrollment\Repositories\EnrollmentRepositoryInterface;
use App\Domain\Enrollment\Repositories\StudentReadRepositoryInterface;
use App\Infrastructure\Events\StudentEnrolledBridgeEvent;
use App\Infrastructure\Intelligence\PgStatDatabaseMonitoringAdapter;
use App\Infrastructure\Intelligence\RecommendationCommandAdapter;
use App\Infrastructure\Persistence\EloquentUnitOfWork;
use App\Infrastructure\Persistence\Enrollment\EloquentEnrollmentRepository;
use App\Infrastructure\Persistence\Idempotency\EloquentIdempotencyStore;
use App\Infrastructure\Persistence\Intelligence\EloquentRecommendationReadRepository;
use App\Infrastructure\Persistence\Outbox\EloquentOutboxRepository;
use App\Infrastructure\Persistence\Student\EloquentStudentReadRepository;
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
        $this->app->bind(StudentReadRepositoryInterface::class, EloquentStudentReadRepository::class);
        $this->app->bind(RecommendationReadRepositoryInterface::class, EloquentRecommendationReadRepository::class);
        $this->app->bind(RecommendationCommandPort::class, RecommendationCommandAdapter::class);
        $this->app->bind(DatabaseMonitoringPort::class, PgStatDatabaseMonitoringAdapter::class);
    }

    public function boot(): void
    {
        Event::listen(StudentEnrolledBridgeEvent::class, RecordStudentEnrolledAudit::class);
    }
}
