<?php

return [

    'enabled' => env('OPTIMIZATION_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Execution mode: observe | recommend | autonomous | safe
    |--------------------------------------------------------------------------
    | observe     — Level 0: metrics + baseline only, NO code/DB changes
    | recommend   — Level 1: RCA + recommendations, human approval required
    | autonomous  — Level 2: low-risk isolated changes with gates + rollback
    | safe        — Circuit-breaker forced observe-only (runtime state override)
    */
    'mode' => env('OPTIMIZATION_MODE', 'observe'),

    'principle' => 'OBSERVE → MEASURE → BASELINE → DETECT → LOCALIZE → DIAGNOSE → OPTIMIZE → VALIDATE → ACCEPT OR ROLLBACK',

    'one_problem_one_change' => true,

    'worker' => [
        'enabled' => env('OPTIMIZATION_WORKER_ENABLED', true),
        'interval_seconds' => (int) env('OPTIMIZATION_WORKER_INTERVAL', 300),
        'lock_ttl_seconds' => (int) env('OPTIMIZATION_WORKER_LOCK_TTL', 600),
        'max_execution_seconds' => (int) env('OPTIMIZATION_WORKER_MAX_EXECUTION', 120),
    ],

    'domains' => [
        'cpu', 'memory', 'cache', 'database', 'disk', 'network',
        'concurrency', 'io', 'algorithms', 'api', 'serialization',
        'ui', 'startup', 'logging', 'background', 'scalability',
    ],

    'anomaly' => [
        'p95_degradation_pct' => (float) env('OPTIMIZATION_P95_DEGRADATION_PCT', 20),
        'p99_degradation_pct' => (float) env('OPTIMIZATION_P99_DEGRADATION_PCT', 25),
        'cpu_degradation_pct' => (float) env('OPTIMIZATION_CPU_DEGRADATION_PCT', 20),
        'memory_degradation_pct' => (float) env('OPTIMIZATION_MEMORY_DEGRADATION_PCT', 20),
        'query_count_increase_pct' => (float) env('OPTIMIZATION_QUERY_INCREASE_PCT', 30),
        'cache_hit_decrease_pct' => (float) env('OPTIMIZATION_CACHE_HIT_DECREASE_PCT', 15),
        'error_rate_threshold_pct' => (float) env('OPTIMIZATION_ERROR_RATE_THRESHOLD', 1.0),
        'consecutive_observations_required' => (int) env('OPTIMIZATION_HYSTERESIS_OBSERVATIONS', 3),
        'sustained_degradation_minutes' => (int) env('OPTIMIZATION_SUSTAINED_DEGRADATION_MINUTES', 15),
    ],

    'baseline' => [
        'min_healthy_observations_to_adapt' => (int) env('OPTIMIZATION_BASELINE_HEALTHY_OBSERVATIONS', 10),
        'adaptation_max_shift_pct' => (float) env('OPTIMIZATION_BASELINE_MAX_SHIFT_PCT', 15),
        'percentiles' => ['p50', 'p75', 'p90', 'p95', 'p99'],
        'prevent_poisoning' => true,
        'context_match_keys' => ['environment', 'cpu_cores', 'memory_limit_mb'],
    ],

    'stabilization' => [
        'window_minutes' => (int) env('OPTIMIZATION_STABILIZATION_MINUTES', 30),
        'min_observations' => (int) env('OPTIMIZATION_STABILIZATION_OBSERVATIONS', 5),
        'max_window_minutes' => (int) env('OPTIMIZATION_STABILIZATION_MAX_MINUTES', 120),
    ],

    'cooldown' => [
        'minutes' => (int) env('OPTIMIZATION_COOLDOWN_MINUTES', 60),
    ],

    'circuit_breaker' => [
        'max_failed_attempts' => (int) env('OPTIMIZATION_MAX_FAILED_ATTEMPTS', 3),
        'recovery_manual_only' => true,
    ],

    'environments' => [
        'production' => ['max_risk_tier_autonomous' => 1, 'require_hysteresis' => true],
        'staging' => ['max_risk_tier_autonomous' => 1, 'require_hysteresis' => true],
        'local' => ['max_risk_tier_autonomous' => 1, 'require_hysteresis' => false],
        'testing' => ['max_risk_tier_autonomous' => 0, 'require_hysteresis' => false],
    ],

    'complexity_gate' => [
        'handler_warn_lines' => 50,
        'handler_fail_lines' => 150,
        'handler_fail_cyclomatic' => 10,
    ],

    'scoring' => [
        'min_confidence_for_autonomous' => (float) env('OPTIMIZATION_MIN_ROOT_CAUSE_CONFIDENCE', 0.75),
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
        // Documentation only — only `analyze` is registered for autonomous execution.
        'analyze',
    ],

    'autonomous_actions' => [
        'analyze',
    ],

    'analyze' => [
        /*
         * Fail-closed: empty/missing allowlist blocks autonomous ANALYZE.
         * Set OPTIMIZATION_ANALYZE_ALLOWED_TARGETS=schema.table for explicit approval.
         */
        'allowed_targets' => env('OPTIMIZATION_ANALYZE_ALLOWED_TARGETS')
            ? array_values(array_filter(array_map('trim', explode(',', (string) env('OPTIMIZATION_ANALYZE_ALLOWED_TARGETS')))))
            : [],
        'execution_timeout_seconds' => (int) env('OPTIMIZATION_ANALYZE_TIMEOUT_SECONDS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Controlled autonomous ANALYZE — environment gate (fail-closed)
    |--------------------------------------------------------------------------
    | Autonomous mode alone is insufficient. Only listed app.env values may run
    | autonomous ANALYZE. Production must NOT appear here.
    */
    'autonomous' => [
        /*
         * Hard block: production must NEVER run autonomous ANALYZE, even if
         * OPTIMIZATION_MODE=autonomous and production appears in controlled_environments.
         */
        'block_production' => env('OPTIMIZATION_AUTONOMOUS_BLOCK_PRODUCTION', true),
        'require_controlled_environment' => env('OPTIMIZATION_AUTONOMOUS_REQUIRE_CONTROLLED_ENV', true),
        'controlled_environments' => env('OPTIMIZATION_AUTONOMOUS_CONTROLLED_ENVIRONMENTS')
            ? array_values(array_filter(array_map('trim', explode(',', (string) env('OPTIMIZATION_AUTONOMOUS_CONTROLLED_ENVIRONMENTS')))))
            : [],
        'kill_switch' => env('OPTIMIZATION_AUTONOMOUS_KILL_SWITCH', false),
        'rate_limits' => [
            'max_per_cycle' => (int) env('OPTIMIZATION_AUTONOMOUS_MAX_PER_CYCLE', 1),
            'max_per_target_per_window' => (int) env('OPTIMIZATION_AUTONOMOUS_MAX_PER_TARGET_PER_HOUR', 3),
            'max_global_per_window' => (int) env('OPTIMIZATION_AUTONOMOUS_MAX_GLOBAL_PER_HOUR', 10),
            'window_minutes' => (int) env('OPTIMIZATION_AUTONOMOUS_RATE_WINDOW_MINUTES', 60),
        ],
    ],

    'data_scale' => [
        'small_max_mb' => (float) env('OPTIMIZATION_DATA_SCALE_SMALL_MB', 1024),
        'medium_max_mb' => (float) env('OPTIMIZATION_DATA_SCALE_MEDIUM_MB', 10240),
        'block_autonomous_on_unknown' => env('OPTIMIZATION_BLOCK_ON_UNKNOWN_SCALE', true),
    ],

    'protected_metrics' => [
        'p95_latency_ms',
        'p99_latency_ms',
        'cpu_pct',
        'memory_mb',
        'db_queries_per_request',
        'error_rate_pct',
        'cache_hit_ratio',
    ],

    'unified_safety_pipeline' => env('OPTIMIZATION_UNIFIED_SAFETY_PIPELINE', true),

    'measurement' => [
        'post_delay_seconds' => (int) env('OPTIMIZATION_POST_MEASUREMENT_DELAY', 2),
    ],

    'gates' => [
        'architecture_validate_before_code_change' => true,
        'intelligence_governance' => true,
        'cross_metric_regression_block' => true,
        'rollback_enabled' => env('OPTIMIZATION_ROLLBACK_ENABLED', true),
        'block_autonomous_on_unknown_critical' => true,
        'cross_metric_latency_regression_pct' => 20,
        'cross_metric_memory_regression_pct' => 20,
        'cross_metric_cache_decrease_pct' => 10,
        'cross_metric_error_regression_pct' => 5,
    ],

    'state_path' => storage_path('app/optimization/state'),
    'history_path' => storage_path('app/optimization/history'),
    'adaptive_baseline_path' => storage_path('app/optimization/adaptive-baseline.json'),
    'environment_profile_path' => storage_path('app/optimization/environment-profile.json'),
    'checkpoints_path' => storage_path('app/optimization/checkpoints'),

    'reports_path' => base_path('.cursor/architecture/optimization'),

    'schedules' => [
        'self_healing_cycle' => 'everyFiveMinutes',
        'baseline_capture' => 'weekly',
        'stabilization_check' => 'everyFiveMinutes',
    ],

];
