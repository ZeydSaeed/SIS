<?php

namespace App\Intelligence\Models;

class QueryMetric extends IntelligenceModel
{
    public $timestamps = false;

    protected $table;

    protected $fillable = [
        'query_fingerprint',
        'query_label',
        'call_count',
        'p50_ms',
        'p95_ms',
        'p99_ms',
        'mean_ms',
        'baseline_p95_ms',
        'degradation_pct',
        'workload_class',
        'context',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'p50_ms' => 'decimal:2',
            'p95_ms' => 'decimal:2',
            'p99_ms' => 'decimal:2',
            'mean_ms' => 'decimal:2',
            'baseline_p95_ms' => 'decimal:2',
            'degradation_pct' => 'decimal:2',
            'context' => 'array',
            'captured_at' => 'datetime',
        ];
    }

    public function __construct(array $attributes = [])
    {
        $this->table = self::intelligenceTable('query_metrics');
        parent::__construct($attributes);
    }
}
