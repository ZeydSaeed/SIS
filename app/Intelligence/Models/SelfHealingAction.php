<?php

namespace App\Intelligence\Models;

class SelfHealingAction extends IntelligenceModel
{
    public $timestamps = false;

    protected $table;

    protected $fillable = [
        'action_code',
        'playbook_id',
        'risk_tier',
        'trigger_reason',
        'action_taken',
        'metrics_before',
        'metrics_after',
        'outcome',
        'auto_executed',
        'correlation_id',
        'executed_at',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'metrics_before' => 'array',
            'metrics_after' => 'array',
            'auto_executed' => 'boolean',
            'executed_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function __construct(array $attributes = [])
    {
        $this->table = self::intelligenceTable('self_healing_actions');
        parent::__construct($attributes);
    }
}
