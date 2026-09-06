<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Database\SchemaHelper;
use Illuminate\Database\Eloquent\Model;

class OutboxMessageRecord extends Model
{
    public $timestamps = false;

    protected $table;

    protected $fillable = [
        'event_type',
        'payload',
        'correlation_id',
        'occurred_at',
        'processed_at',
        'attempts',
        'created_at',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = SchemaHelper::qualified('audit', 'outbox_messages');
        parent::__construct($attributes);
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
            'processed_at' => 'datetime',
            'created_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }
}
