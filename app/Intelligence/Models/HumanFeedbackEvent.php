<?php

namespace App\Intelligence\Models;

class HumanFeedbackEvent extends IntelligenceModel
{
    public $timestamps = false;

    protected $table;

    protected $fillable = [
        'recommendation_id',
        'human_decision',
        'human_decision_reason',
        'human_chose_instead',
        'outcome_of_human_choice',
        'user_id',
        'correlation_id',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = self::intelligenceTable('human_feedback_events');
        parent::__construct($attributes);
    }
}
