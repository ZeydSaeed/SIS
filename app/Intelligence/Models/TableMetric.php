<?php

namespace App\Intelligence\Models;

class TableMetric extends IntelligenceModel
{
    public $timestamps = false;

    protected $table;

    protected $fillable = [
        'schema_name',
        'table_name',
        'row_estimate',
        'table_size_mb',
        'index_size_mb',
        'seq_scan_ratio',
        'seq_scans',
        'idx_scans',
        'growth_rate_daily_pct',
        'context',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'table_size_mb' => 'decimal:2',
            'index_size_mb' => 'decimal:2',
            'seq_scan_ratio' => 'decimal:4',
            'growth_rate_daily_pct' => 'decimal:2',
            'context' => 'array',
            'captured_at' => 'datetime',
        ];
    }

    public function __construct(array $attributes = [])
    {
        $this->table = self::intelligenceTable('table_metrics');
        parent::__construct($attributes);
    }

    public function qualifiedName(): string
    {
        return "{$this->schema_name}.{$this->table_name}";
    }

    public function tableSizeGb(): float
    {
        return ((float) $this->table_size_mb + (float) $this->index_size_mb) / 1024;
    }
}
