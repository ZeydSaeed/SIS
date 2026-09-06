<?php

namespace App\Intelligence\Models;

class MonitoringSnapshot extends IntelligenceModel
{
    public $timestamps = false;

    protected $table;

    protected $fillable = [
        'snapshot_type',
        'database_size_mb',
        'connection_count',
        'active_connections',
        'cache_hit_ratio',
        'replication_lag_seconds',
        'metrics',
        'correlation_id',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'database_size_mb' => 'decimal:2',
            'cache_hit_ratio' => 'decimal:4',
            'replication_lag_seconds' => 'decimal:2',
            'metrics' => 'array',
            'captured_at' => 'datetime',
        ];
    }

    public function __construct(array $attributes = [])
    {
        $this->table = self::intelligenceTable('monitoring_snapshots');
        parent::__construct($attributes);
    }
}
