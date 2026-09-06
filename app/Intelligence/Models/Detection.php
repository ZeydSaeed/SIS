<?php

namespace App\Intelligence\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Detection extends IntelligenceModel
{
    protected $table;

    protected $fillable = [
        'detection_code',
        'rule_id',
        'risk_tier',
        'severity',
        'schema_name',
        'table_name',
        'query_fingerprint',
        'title',
        'diagnosis',
        'evidence',
        'context_fingerprint',
        'degradation_score',
        'status',
        'correlation_id',
        'detected_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'context_fingerprint' => 'array',
            'degradation_score' => 'decimal:2',
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function __construct(array $attributes = [])
    {
        $this->table = self::intelligenceTable('detections');
        parent::__construct($attributes);
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class);
    }
}
