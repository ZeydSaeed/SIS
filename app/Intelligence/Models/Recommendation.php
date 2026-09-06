<?php

namespace App\Intelligence\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recommendation extends IntelligenceModel
{
    protected $table;

    protected $fillable = [
        'recommendation_code',
        'detection_id',
        'rule_id',
        'risk_tier',
        'action_type',
        'schema_name',
        'table_name',
        'what',
        'why',
        'evidence',
        'alternatives',
        'expected_impact',
        'cost_analysis',
        'rollback_plan',
        'confidence',
        'context_similarity',
        'blast_radius_score',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'correlation_id',
    ];

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'alternatives' => 'array',
            'expected_impact' => 'array',
            'cost_analysis' => 'array',
            'confidence' => 'decimal:4',
            'context_similarity' => 'decimal:4',
            'approved_at' => 'datetime',
        ];
    }

    public function __construct(array $attributes = [])
    {
        $this->table = self::intelligenceTable('recommendations');
        parent::__construct($attributes);
    }

    public function detection(): BelongsTo
    {
        return $this->belongsTo(Detection::class);
    }

    public function optimizationEvents(): HasMany
    {
        return $this->hasMany(OptimizationEvent::class);
    }

    public function feedbackEvents(): HasMany
    {
        return $this->hasMany(HumanFeedbackEvent::class);
    }
}
