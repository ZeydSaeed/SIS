<?php

$profile = (string) env('SIS_ENV_PROFILE', env('APP_ENV', 'local'));

$profiles = [
    'local' => [
        'database_connection' => 'pgsql',
        'cache_store' => 'redis',
        'queue_connection' => 'redis',
        'intelligence_scheduler' => true,
        'optimization_scheduler' => true,
        'optimization_mode' => 'observe',
    ],
    'testing' => [
        'database_connection' => 'sqlite',
        'cache_store' => 'array',
        'queue_connection' => 'sync',
        'intelligence_scheduler' => false,
        'optimization_scheduler' => false,
        'optimization_mode' => 'observe',
    ],
    'staging' => [
        'database_connection' => 'pgsql',
        'cache_store' => 'redis',
        'queue_connection' => 'redis',
        'intelligence_scheduler' => true,
        'optimization_scheduler' => true,
        'optimization_mode' => 'observe',
    ],
    'production' => [
        'database_connection' => 'pgsql',
        'cache_store' => 'redis',
        'queue_connection' => 'redis',
        'intelligence_scheduler' => true,
        'optimization_scheduler' => true,
        'optimization_mode' => 'observe',
    ],
];

$active = $profiles[$profile] ?? $profiles['local'];

return [

    /*
    |--------------------------------------------------------------------------
    | SIS Environment Profile
    |--------------------------------------------------------------------------
    |
    | Aligns runtime expectations across database, cache, queue, and schedulers.
    | Set SIS_ENV_PROFILE explicitly when APP_ENV differs (e.g. APP_ENV=local
    | with SIS_ENV_PROFILE=staging for soak tests).
    |
    | Profiles: local | testing | staging | production
    |
    */

    'environment_profile' => $profile,

    'profiles' => $profiles,

    'active_profile' => $active,

    'api' => [
        'version' => env('SIS_API_VERSION', '0.1.0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduler Gates (profile-aware, overridable via env)
    |--------------------------------------------------------------------------
    |
    | When unset, defaults come from the active profile. Testing profile disables
    | intelligence/optimization schedulers to keep PHPUnit fast and isolated.
    |
    */

    'scheduler' => [
        'intelligence_enabled' => env('SIS_SCHEDULER_INTELLIGENCE') !== null
            ? filter_var(env('SIS_SCHEDULER_INTELLIGENCE'), FILTER_VALIDATE_BOOLEAN)
            : ($active['intelligence_scheduler'] ?? true),
        'optimization_enabled' => env('SIS_SCHEDULER_OPTIMIZATION') !== null
            ? filter_var(env('SIS_SCHEDULER_OPTIMIZATION'), FILTER_VALIDATE_BOOLEAN)
            : ($active['optimization_scheduler'] ?? true),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Request Observability (Phase 3.4)
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | Workflow auto-hooks (Phase WF-HOOKS)
    |--------------------------------------------------------------------------
    */

    'workflow' => [
        'auto_hooks' => [
            'transfer_request' => env('SIS_WF_HOOK_TRANSFER', true),
            'transfer_decide_sync' => env('SIS_WF_HOOK_TRANSFER_DECIDE_SYNC', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Observability
    |--------------------------------------------------------------------------
    */

    'observability' => [
        'http_enabled' => env('SIS_HTTP_TELEMETRY_ENABLED', true),
        'slow_query_threshold_ms' => (int) env('SIS_SLOW_QUERY_THRESHOLD_MS', 10),
        'metrics' => [
            'enabled' => env('SIS_METRICS_ENABLED', true),
            'token' => env('SIS_METRICS_TOKEN', ''),
            'allow_unauthenticated_in' => ['local', 'testing'],
        ],
        'workloads' => [
            // Exact route overrides (highest priority)
            'routes' => [
                'api.students.search' => 'student_search',
                'api.students.index' => 'student_search',
                'api.students.show' => 'student_search',
                'api.students.store' => 'student_search',
                'api.students.update' => 'student_search',
                'api.health' => 'health',
                'api.metrics' => 'health',
            ],
            // Prefix fallback — longest prefix wins when ordered longest-first in resolver
            'prefixes' => [
                'api.finance.' => 'finance_oltp',
                'api.communication.' => 'communication_oltp',
                'api.workflow.' => 'workflow_oltp',
                'api.documents.' => 'documents_oltp',
                'api.transfers.' => 'transfers_oltp',
                'api.teachers.' => 'teachers_oltp',
                'api.promotion.' => 'promotion_oltp',
                'api.attendance.' => 'attendance_oltp',
                'api.timetable.' => 'timetable_oltp',
                'api.results.' => 'results_oltp',
                'api.portal.' => 'portal_results',
                'api.vocational.' => 'vocational_oltp',
                'api.exam_sessions.' => 'exams_oltp',
                'api.exam_enrollments.' => 'exams_oltp',
                'api.enrollments.' => 'enrollment_oltp',
                'api.grades' => 'exams_oltp',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Workload Validation Profiles (Phase 3.6)
    |--------------------------------------------------------------------------
    */

    'workload_validation' => [
        'profiles' => [
            'student_search' => [
                'seed_students' => 50,
                'warmup_requests' => 5,
                'sample_requests' => 30,
                'search_terms' => ['Ali', 'Sara', 'Omar', 'Noor', 'Hassan'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Safety (Phase 2 Remediation)
    |--------------------------------------------------------------------------
    |
    | protected_names: never allow RefreshDatabase / migrate:fresh / schema wipe
    | destructive_allowed_names: disposable DBs only (sis_test, :memory:, …)
    | force_protected: emergency kill-switch (treat current connection as protected)
    |
    */

    'database' => [
        'protected_names' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('SIS_PROTECTED_DATABASES', 'sis'))
        ))),
        'destructive_allowed_names' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('SIS_DESTRUCTIVE_ALLOWED_DATABASES', 'sis_test,:memory:'))
        ))),
        'force_protected' => filter_var(env('SIS_DATABASE_FORCE_PROTECTED', false), FILTER_VALIDATE_BOOLEAN),
        'pgsql_test_database' => env('SIS_PGSQL_TEST_DATABASE', 'sis_test'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Graduation authority (Phase 3C.16)
    |--------------------------------------------------------------------------
    |
    | Fail-closed actor allow-lists until HD-31-G locks Permission.php catalog.
    | Empty list = deny. Do not invent graduation.* permission strings here.
    |
    */

    'graduation' => [
        'authority' => [
            'evaluate_completion' => [],
            'create_completion_outcome' => [],
            'approve_graduation' => [],
            'issue_award' => [],
            'revoke_award' => [],
            'publish_award' => [],
        ],
    ],

];
