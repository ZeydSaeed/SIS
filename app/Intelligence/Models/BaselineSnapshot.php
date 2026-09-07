<?php

namespace App\Intelligence\Models;

class BaselineSnapshot extends IntelligenceModel
{
    protected $table;

    protected $fillable = [
        'baseline_code',
        'label',
        'context_fingerprint',
        'table_sizes',
        'query_baselines',
        'active_patterns',
        'knowledge_version',
        'captured_at',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = self::intelligenceTable('baseline_snapshots');
        parent::__construct($attributes);
    }

    protected function casts(): array
    {
        return [
            'context_fingerprint' => 'array',
            'table_sizes' => 'array',
            'query_baselines' => 'array',
            'active_patterns' => 'array',
            'captured_at' => 'datetime',
        ];
    }
}
