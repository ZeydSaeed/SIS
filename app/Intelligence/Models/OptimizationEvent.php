<?php

namespace App\Intelligence\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OptimizationEvent extends IntelligenceModel
{
    protected $table;

    protected $fillable = [
        'event_code',
        'recommendation_id',
        'type',
        'rule_id',
        'risk_tier',
        'schema_name',
        'table_name',
        'trigger',
        'action_taken',
        'evidence_before',
        'evidence_after',
        'results',
        'outcome',
        'rollback_required',
        'rollback_verified',
        'rollback_action',
        'executed_by',
        'approved_by',
        'simulation_id',
        'context_fingerprint',
        'recency_weight',
        'correlation_id',
        'executed_at',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'trigger' => 'array',
            'evidence_before' => 'array',
            'evidence_after' => 'array',
            'results' => 'array',
            'context_fingerprint' => 'array',
            'rollback_required' => 'boolean',
            'rollback_verified' => 'boolean',
            'recency_weight' => 'decimal:2',
            'executed_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function __construct(array $attributes = [])
    {
        $this->table = self::intelligenceTable('optimization_events');
        parent::__construct($attributes);
    }

    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(Recommendation::class);
    }
}
