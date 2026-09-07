<?php

return [

    'enabled' => env('OPTIMIZATION_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Execution mode: observe | recommend | autonomous
    |--------------------------------------------------------------------------
    | observe     — Level 0: metrics + baseline only, NO code/DB changes
    | recommend   — Level 1: RCA + recommendations, human approval required
    | autonomous  — Level 2: low-risk isolated changes with gates + rollback
    */
    'mode' => env('OPTIMIZATION_MODE', 'observe'),

    'principle' => 'DETECT → MEASURE → BASELINE → ANALYZE → ISOLATE → OPTIMIZE → VALIDATE → ACCEPT OR ROLLBACK',

    'one_problem_one_change' => true,

    'domains' => [
        'cpu', 'memory', 'cache', 'database', 'disk', 'network',
        'concurrency', 'io', 'algorithms', 'api', 'serialization',
        'ui', 'startup', 'logging', 'background', 'scalability',
    ],

    'anomaly' => [
        'p95_degradation_pct' => (float) env('OPTIMIZATION_P95_DEGRADATION_PCT', 20),
        'query_count_increase_pct' => (float) env('OPTIMIZATION_QUERY_INCREASE_PCT', 30),
    ],

    'complexity_gate' => [
        'handler_warn_lines' => 50,
        'handler_fail_lines' => 150,
        'handler_fail_cyclomatic' => 10,
    ],

    'scoring' => [
        'min_confidence_for_autonomous' => 0.75,
        'max_risk_tier_autonomous' => 1,
    ],

    'safety_boundaries' => [
        'require_approval' => [
            'database_schema',
            'migration',
            'authentication',
            'authorization',
            'financial_calculation',
            'grade_calculation',
            'transaction_semantics',
            'concurrency_semantics',
            'public_api_breaking',
            'audit_log_removal',
        ],
    ],

    'low_risk_auto_actions' => [
        'analyze',
        'cache_ttl_adjust',
        'query_projection',
        'eager_load',
        'remove_redundant_computation',
    ],

    'protected_metrics' => [
        'p95_latency_ms',
        'cpu_pct',
        'memory_mb',
        'db_queries_per_request',
        'error_rate_pct',
        'cache_hit_ratio',
    ],

    'gates' => [
        'architecture_validate_before_code_change' => true,
        'intelligence_governance' => true,
        'cross_metric_regression_block' => true,
    ],

    'history_path' => storage_path('app/optimization/history'),

    'reports_path' => base_path('.cursor/architecture/optimization'),

    'schedules' => [
        'observe' => 'everyFiveMinutes',
        'baseline_capture' => 'weekly',
    ],

];
