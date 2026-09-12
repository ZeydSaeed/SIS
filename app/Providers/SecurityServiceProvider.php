<?php

namespace App\Providers;

use App\Infrastructure\Persistence\Eloquent\AttendanceSessionRecord;
use App\Infrastructure\Persistence\Eloquent\EnrollmentRecord;
use App\Infrastructure\Persistence\Eloquent\ExamRecord;
use App\Infrastructure\Persistence\Eloquent\ScheduleExceptionRecord;
use App\Infrastructure\Persistence\Eloquent\ScheduleRecord;
use App\Infrastructure\Persistence\Eloquent\SpecializationRecord;
use App\Infrastructure\Persistence\Eloquent\SpecializationSubjectRecord;
use App\Infrastructure\Persistence\Eloquent\StudentGradeRecord;
use App\Infrastructure\Persistence\Eloquent\StudentRecord;
use App\Infrastructure\Persistence\Eloquent\TermResultRecord;
use App\Infrastructure\Persistence\Eloquent\TrackRecord;
use App\Models\User;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityAuditLogger;
use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use App\Security\Authorization\DatabaseAuthorizationService;
use App\Security\Authorization\Permission;
use App\Security\Authorization\SchoolScopeService;
use App\Security\Context\SchoolContext;
use App\Security\Policies\AttendancePolicy;
use App\Security\Policies\EnrollmentPolicy;
use App\Security\Policies\ExamPolicy;
use App\Security\Policies\GradePolicy;
use App\Security\Policies\ResultsPolicy;
use App\Security\Policies\StudentPolicy;
use App\Security\Policies\TimetablePolicy;
use App\Security\Policies\VocationalPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class SecurityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(SchoolContext::class);
        $this->app->singleton(SchoolScopeService::class);
        $this->app->singleton(AuthorizationServiceInterface::class, DatabaseAuthorizationService::class);
        $this->app->singleton(SecurityAuditLoggerInterface::class, SecurityAuditLogger::class);
    }

    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configurePolicies();
        $this->configureGates();
    }

    private function configurePolicies(): void
    {
        Gate::policy(StudentRecord::class, StudentPolicy::class);
        Gate::policy(EnrollmentRecord::class, EnrollmentPolicy::class);
        Gate::policy(StudentGradeRecord::class, GradePolicy::class);
        Gate::policy(ExamRecord::class, ExamPolicy::class);
        Gate::policy(AttendanceSessionRecord::class, AttendancePolicy::class);
        Gate::policy(ScheduleRecord::class, TimetablePolicy::class);
        Gate::policy(ScheduleExceptionRecord::class, TimetablePolicy::class);
        Gate::policy(TermResultRecord::class, ResultsPolicy::class);
        Gate::policy(SpecializationRecord::class, VocationalPolicy::class);
        Gate::policy(TrackRecord::class, VocationalPolicy::class);
        Gate::policy(SpecializationSubjectRecord::class, VocationalPolicy::class);
    }

    private function configureGates(): void
    {
        Gate::define('security.manage_users', function (User $user): bool {
            return app(AuthorizationServiceInterface::class)
                ->userHasPermission($user, Permission::SECURITY_MANAGE_USERS);
        });
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            $config = config('security.rate_limits.api', ['max_attempts' => 120, 'decay_minutes' => 1]);

            return Limit::perMinutes(
                (int) ($config['decay_minutes'] ?? 1),
                (int) ($config['max_attempts'] ?? 120),
            )->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('api-students', function (Request $request): Limit {
            $config = config('security.rate_limits.api_students', ['max_attempts' => 60, 'decay_minutes' => 1]);

            return Limit::perMinutes(
                (int) ($config['decay_minutes'] ?? 1),
                (int) ($config['max_attempts'] ?? 60),
            )->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('api-search', function (Request $request): Limit {
            $config = config('security.rate_limits.api_search', ['max_attempts' => 30, 'decay_minutes' => 1]);

            return Limit::perMinutes(
                (int) ($config['decay_minutes'] ?? 1),
                (int) ($config['max_attempts'] ?? 30),
            )->by($request->user()?->id ?: $request->ip());
        });
    }
}
