<?php

return [

    'enabled' => env('INTELLIGENCE_ENABLED', true),

    'knowledge_version' => env('INTELLIGENCE_KB_VERSION', 'KB-2026.09'),

    'performance_budget_version' => env('INTELLIGENCE_PB_VERSION', 'PB-2026.09'),

    /*
    |--------------------------------------------------------------------------
    | Risk Tiers (aligned with DATABASE-INTELLIGENCE-SAFETY.md)
    |--------------------------------------------------------------------------
    |
    | 0 = Observe only
    | 1 = Safe auto (ANALYZE, cache TTL, replica route signals)
    | 2 = Human approval required
    | 3 = ADR + DBA required
    | 4 = Forbidden auto
    |
    */

    'auto_execute_max_tier' => (int) env('INTELLIGENCE_AUTO_MAX_TIER', 1),

    'forbidden_auto_actions' => [
        'drop_index',
        'drop_column',
        'drop_table',
        'alter_column_type',
        'partition_create',
        'partition_attach',
        'disable_rls',
        'drop_foreign_key',
        'schema_restructure',
        'normalization_change',
    ],

    'self_healing' => [
        'enabled' => env('INTELLIGENCE_SELF_HEALING', true),
        'max_workers' => (int) env('INTELLIGENCE_MAX_WORKERS', 20),
        'max_connections_pct' => (int) env('INTELLIGENCE_MAX_CONNECTIONS_PCT', 85),
        'max_auto_scale_multiplier' => (float) env('INTELLIGENCE_MAX_AUTO_SCALE', 2.0),
        'replica_lag_threshold_seconds' => (int) env('INTELLIGENCE_REPLICA_LAG_SECONDS', 30),
        'connection_saturation_threshold_pct' => (int) env('INTELLIGENCE_CONNECTION_SATURATION_PCT', 85),
    ],

    'ops_self_healing' => [
        'enabled' => env('INTELLIGENCE_OPS_SELF_HEALING', true),
        'allowlist' => ['replica_lag', 'connection_saturation'],
        'cooldown_minutes' => (int) env('INTELLIGENCE_OPS_COOLDOWN_MINUTES', 15),
        'max_failures' => (int) env('INTELLIGENCE_OPS_MAX_FAILURES', 5),
    ],

    'verification' => [
        'window_minutes' => (int) env('INTELLIGENCE_VERIFY_WINDOW', 15),
        'success_p95_reduction_pct' => (float) env('INTELLIGENCE_SUCCESS_P95_REDUCTION', 30.0),
        'regression_p95_increase_pct' => (float) env('INTELLIGENCE_REGRESSION_P95_INCREASE', 20.0),
    ],

    'learning' => [
        'recency_weights' => [
            ['max_age_months' => 6, 'weight' => 1.0],
            ['max_age_months' => 12, 'weight' => 0.8],
            ['max_age_months' => 24, 'weight' => 0.6],
            ['max_age_months' => null, 'weight' => 0.4],
        ],
        'min_samples_raw' => 20,
        'min_samples_context_matched' => 10,
        'pattern_deprecate_success_rate' => 0.40,
        'drift_score_threshold' => 0.50,
    ],

    'schedules' => [
        'health_monitor' => env('INTELLIGENCE_SCHEDULE_HEALTH', 'everyMinute'),
        'performance_analyzer' => env('INTELLIGENCE_SCHEDULE_PERFORMANCE', 'everyFiveMinutes'),
        'growth_analyzer' => env('INTELLIGENCE_SCHEDULE_GROWTH', 'hourly'),
        'optimization_analysis' => env('INTELLIGENCE_SCHEDULE_OPTIMIZATION', 'daily'),
        'learning_analysis' => env('INTELLIGENCE_SCHEDULE_LEARNING', 'weekly'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Adaptive threshold rules (static starting points — evidence replaces over time)
    |--------------------------------------------------------------------------
    */

    'threshold_rules' => [
        [
            'id' => 'large_table',
            'risk_tier' => 0,
            'condition' => ['table_size_gb' => '> 10'],
            'action' => 'analyze_table',
            'message' => 'Table exceeds 10 GB — schedule analysis',
        ],
        [
            'id' => 'extreme_table',
            'risk_tier' => 0,
            'condition' => ['table_size_gb' => '> 100'],
            'action' => 'partition_review',
            'message' => 'Table exceeds 100 GB — partition candidate review',
        ],
        [
            'id' => 'high_p95',
            'risk_tier' => 0,
            'condition' => ['query_p95_ms' => '> 500'],
            'action' => 'investigate',
            'message' => 'Query P95 exceeds performance budget investigation threshold',
        ],
        [
            'id' => 'extreme_p95',
            'risk_tier' => 0,
            'condition' => ['query_p95_ms' => '> 2000'],
            'action' => 'critical_investigate',
            'message' => 'Query P95 critically degraded',
        ],
        [
            'id' => 'index_candidate',
            'risk_tier' => 2,
            'condition' => ['sequential_scan_ratio' => '> 0.80', 'table_rows' => '> 100000'],
            'action' => 'suggest_index',
            'message' => 'High sequential scan ratio on large table — index candidate',
        ],
        [
            'id' => 'performance_degradation',
            'risk_tier' => 0,
            'condition' => ['degradation_pct' => '> 100'],
            'action' => 'degradation_detected',
            'message' => 'Performance degraded more than 100% vs baseline',
        ],
        [
            'id' => 'connection_saturation',
            'risk_tier' => 1,
            'condition' => ['connection_usage_pct' => '> 85'],
            'action' => 'self_heal_connections',
            'message' => 'Connection pool near saturation',
        ],
        [
            'id' => 'replica_lag',
            'risk_tier' => 1,
            'condition' => ['replication_lag_seconds' => '> 30'],
            'action' => 'route_reads_primary',
            'message' => 'Replication lag exceeds safe threshold',
        ],
        [
            'id' => 'statistics_stale',
            'risk_tier' => 1,
            'condition' => ['stats_age_hours' => '> 168'],
            'action' => 'analyze_auto',
            'message' => 'Table statistics stale — safe ANALYZE candidate',
        ],
        [
            'id' => 'growth_abnormal',
            'risk_tier' => 0,
            'condition' => ['growth_rate_daily_pct' => '> 50'],
            'action' => 'growth_review',
            'message' => 'Abnormal daily table growth detected',
        ],
        [
            'id' => 'http_budget_exceeded',
            'risk_tier' => 0,
            'condition' => ['budget_exceeded' => 'true'],
            'action' => 'investigate',
            'message' => 'HTTP workload exceeded performance budget P95',
        ],
        [
            'id' => 'http_high_error_rate',
            'risk_tier' => 0,
            'condition' => ['error_rate_pct' => '> 5'],
            'action' => 'investigate',
            'message' => 'HTTP workload error rate exceeds safe threshold',
        ],
        [
            'id' => 'http_query_heavy',
            'risk_tier' => 0,
            'condition' => ['mean_db_queries_per_request' => '> 10'],
            'action' => 'investigate',
            'message' => 'High DB query count per HTTP request detected',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Expert inference rules (subset of DATABASE-KNOWLEDGE-BASE.md)
    |--------------------------------------------------------------------------
    */

    'expert_rules' => [
        [
            'id' => 'IDX-007',
            'risk_tier' => 2,
            'when' => ['sequential_scan_ratio' => '> 0.80', 'table_rows' => '> 100000'],
            'diagnosis' => 'Frequent sequential scans on large table — missing or mismatched index',
            'recommendation_type' => 'index_add',
            'alternatives' => ['materialized_view', 'query_rewrite'],
        ],
        [
            'id' => 'PART-003',
            'risk_tier' => 3,
            'when' => ['table_size_gb' => '> 10', 'partition_pruning_rate' => '< 0.50'],
            'diagnosis' => 'Large table with poor partition pruning — partition strategy mismatch',
            'recommendation_type' => 'partition_review',
            'alternatives' => ['sub_partition', 'archive_old_years'],
        ],
        [
            'id' => 'MAINT-001',
            'risk_tier' => 1,
            'when' => ['stats_age_hours' => '> 168'],
            'diagnosis' => 'Stale planner statistics causing suboptimal plans',
            'recommendation_type' => 'analyze',
            'alternatives' => [],
        ],
        [
            'id' => 'NORM-001',
            'risk_tier' => 3,
            'when' => ['normalization_violation' => true],
            'diagnosis' => 'Schema normalization violation detected — structural review required',
            'recommendation_type' => 'schema_review',
            'alternatives' => ['migration_proposal'],
        ],
        [
            'id' => 'FK-001',
            'risk_tier' => 2,
            'when' => ['missing_foreign_key' => true],
            'diagnosis' => 'Relationship column without foreign key constraint',
            'recommendation_type' => 'add_foreign_key',
            'alternatives' => [],
        ],
        [
            'id' => 'APP-001',
            'risk_tier' => 0,
            'when' => ['budget_exceeded' => 'true'],
            'diagnosis' => 'Application workload P95 exceeds configured performance budget — investigate API latency and query patterns',
            'recommendation_type' => 'investigate',
            'alternatives' => ['query_optimize', 'cache_warm'],
        ],
        [
            'id' => 'APP-002',
            'risk_tier' => 0,
            'when' => ['mean_db_queries_per_request' => '> 10'],
            'diagnosis' => 'High DB query count per HTTP request — N+1 or missing eager load candidate',
            'recommendation_type' => 'query_optimize',
            'alternatives' => ['index_add', 'cache_read'],
        ],
        [
            'id' => 'APP-003',
            'risk_tier' => 0,
            'when' => ['error_rate_pct' => '> 5'],
            'diagnosis' => 'Elevated HTTP error rate on workload — application or dependency failure',
            'recommendation_type' => 'investigate',
            'alternatives' => [],
        ],
    ],

    'performance_budgets' => [
        'student_search' => [
            'workload' => 'oltp',
            'p95_ms' => 200,
            'p99_ms' => 500,
            // Measured after Phase 3.10.1 security controls (auth, school scope, audit persist): ~4.2 mean queries/request
            'max_db_queries_per_request' => 5,
        ],
        'school_dashboard' => ['workload' => 'dashboard', 'p95_ms' => 1000, 'p99_ms' => 2500, 'max_db_queries_per_request' => 2],
        'directorate_dashboard' => ['workload' => 'dashboard', 'p95_ms' => 3000, 'p99_ms' => 6000, 'max_db_queries_per_request' => 1],
        'attendance_batch' => ['workload' => 'bulk', 'p95_ms' => 2000, 'p99_ms' => 5000, 'max_db_queries_per_request' => 2],
    ],

    'pg_stat_statements' => [
        'enabled' => env('INTELLIGENCE_PG_STAT', true),
        'top_queries' => (int) env('INTELLIGENCE_PG_STAT_TOP', 50),
    ],

    'schema_guardian' => [
        'required_academic_schemas' => [
            'enrollment', 'attendance', 'exams', 'results',
        ],
        'academic_year_column_tables' => [
            'enrollment.enrollments',
            'attendance.records',
            'attendance.sessions',
            'exams.student_grades',
        ],
        'critical_tables' => [
            'exams.student_grades',
            'enrollment.enrollments',
            'attendance.records',
            'students.students',
        ],
    ],

    'cost_weights' => [
        'oltp' => ['performance' => 0.40, 'storage' => 0.15, 'maintenance' => 0.15, 'complexity' => 0.10, 'risk' => 0.20],
        'dashboard' => ['performance' => 0.35, 'storage' => 0.20, 'maintenance' => 0.20, 'complexity' => 0.10, 'risk' => 0.15],
        'bulk' => ['performance' => 0.30, 'storage' => 0.10, 'maintenance' => 0.20, 'complexity' => 0.10, 'risk' => 0.30],
    ],

];
