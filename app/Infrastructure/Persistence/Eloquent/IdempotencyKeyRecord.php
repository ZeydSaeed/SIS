<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Database\SchemaHelper;
use Illuminate\Database\Eloquent\Model;

class IdempotencyKeyRecord extends Model
{
    public $timestamps = false;

    protected $table;

    protected $primaryKey = null;

    public $incrementing = false;

    protected $fillable = [
        'key',
        'command_name',
        'response_payload',
        'created_at',
        'expires_at',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = SchemaHelper::qualified('audit', 'idempotency_keys');
        parent::__construct($attributes);
    }

    protected function casts(): array
    {
        return [
            'response_payload' => 'array',
            'created_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
