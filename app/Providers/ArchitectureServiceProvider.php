<?php

namespace App\Providers;

use App\Application\Contracts\DomainEventDispatcher;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Enrollment\Repositories\EnrollmentRepositoryInterface;
use App\Domain\Enrollment\Repositories\StudentReadRepositoryInterface;
use App\Infrastructure\Events\LaravelDomainEventDispatcher;
use App\Infrastructure\Events\StudentEnrolledBridgeEvent;
use App\Infrastructure\Persistence\EloquentUnitOfWork;
use App\Infrastructure\Persistence\Enrollment\EloquentEnrollmentRepository;
use App\Infrastructure\Persistence\Student\EloquentStudentReadRepository;
use App\Listeners\Enrollment\RecordStudentEnrolledAudit;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class ArchitectureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(UnitOfWork::class, EloquentUnitOfWork::class);
        $this->app->singleton(DomainEventDispatcher::class, LaravelDomainEventDispatcher::class);

        $this->app->bind(EnrollmentRepositoryInterface::class, EloquentEnrollmentRepository::class);
        $this->app->bind(StudentReadRepositoryInterface::class, EloquentStudentReadRepository::class);
    }

    public function boot(): void
    {
        Event::listen(StudentEnrolledBridgeEvent::class, RecordStudentEnrolledAudit::class);
    }
}
