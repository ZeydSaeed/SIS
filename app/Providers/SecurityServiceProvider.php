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
use App\Infrastructure\Persistence\Eloquent\WorkshopRecord;
use App\Models\User;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityAuditLogger;
use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use App\Security\Authorization\DatabaseAuthorizationService;
use App\Security\Authorization\Permission;
use App\Security\Authorization\SchoolScopeService;
use App\Security\Context\SchoolContext;
use App\Security\Policies\AttendancePolicy;
use App\Security\Policies\CommunicationPolicy;
use App\Security\Policies\CurriculumPolicy;
use App\Security\Policies\DocumentsPolicy;
use App\Security\Policies\FinancePolicy;
use App\Security\Policies\HrPolicy;
use App\Security\Policies\WorkflowPolicy;
use App\Security\Policies\EnrollmentPolicy;
use App\Security\Policies\ExamPolicy;
use App\Security\Policies\GradePolicy;
use App\Security\Policies\PortalResultsPolicy;
use App\Security\Policies\PortalScopesPolicy;
use App\Security\Policies\PromotionPolicy;
use App\Security\Policies\ResultsPolicy;
use App\Security\Policies\StudentPolicy;
use App\Security\Policies\TeacherPolicy;
use App\Security\Policies\TransfersPolicy;
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
        Gate::policy(WorkshopRecord::class, VocationalPolicy::class);
    }

    private function configureGates(): void
    {
        Gate::define('viewPortalOfficial', function (User $user): bool {
            return app(PortalResultsPolicy::class)->viewPortal($user);
        });

        Gate::define('managePortalScopes', function (User $user): bool {
            return app(PortalScopesPolicy::class)->manage($user);
        });

        Gate::define('viewTeachers', function (User $user): bool {
            return app(TeacherPolicy::class)->view($user);
        });

        Gate::define('manageTeachers', function (User $user): bool {
            return app(TeacherPolicy::class)->manage($user);
        });

        Gate::define('viewPromotion', function (User $user): bool {
            return app(PromotionPolicy::class)->view($user);
        });

        Gate::define('managePromotion', function (User $user): bool {
            return app(PromotionPolicy::class)->manage($user);
        });

        Gate::define('viewTransfers', function (User $user): bool {
            return app(TransfersPolicy::class)->view($user);
        });

        Gate::define('manageTransfers', function (User $user): bool {
            return app(TransfersPolicy::class)->manage($user);
        });

        Gate::define('viewDocuments', function (User $user): bool {
            return app(DocumentsPolicy::class)->view($user);
        });

        Gate::define('manageDocuments', function (User $user): bool {
            return app(DocumentsPolicy::class)->manage($user);
        });

        Gate::define('viewFinance', function (User $user): bool {
            return app(FinancePolicy::class)->view($user);
        });

        Gate::define('manageFinance', function (User $user): bool {
            return app(FinancePolicy::class)->manage($user);
        });

        Gate::define('viewCommunication', function (User $user): bool {
            return app(CommunicationPolicy::class)->view($user);
        });

        Gate::define('manageCommunication', function (User $user): bool {
            return app(CommunicationPolicy::class)->manage($user);
        });

        Gate::define('viewWorkflow', function (User $user): bool {
            return app(WorkflowPolicy::class)->view($user);
        });

        Gate::define('manageWorkflow', function (User $user): bool {
            return app(WorkflowPolicy::class)->manage($user);
        });

        Gate::define('decideWorkflow', function (User $user): bool {
            return app(WorkflowPolicy::class)->decide($user);
        });

        Gate::define('viewHr', function (User $user): bool {
            return app(HrPolicy::class)->view($user);
        });

        Gate::define('manageHr', function (User $user): bool {
            return app(HrPolicy::class)->manage($user);
        });

        Gate::define('viewCurriculum', function (User $user): bool {
            return app(CurriculumPolicy::class)->view($user);
        });

        Gate::define('manageCurriculum', function (User $user): bool {
            return app(CurriculumPolicy::class)->manage($user);
        });

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
