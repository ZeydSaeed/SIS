<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Database\SchemaHelper;
use Illuminate\Database\Eloquent\Model;

class SecurityAuditLogRecord extends Model
{
    public $timestamps = false;

    protected $table;

    /** @var list<string> */
    protected $fillable = [
        'event_id',
        'occurred_at',
        'actor_id',
        'actor_type',
        'action',
        'target_type',
        'target_id',
        'school_id',
        'result',
        'correlation_id',
        'metadata',
        'created_at',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = SchemaHelper::qualified('security', 'security_audit_logs');
        parent::__construct($attributes);
    }

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
