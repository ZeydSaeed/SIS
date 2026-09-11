<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Database\SchemaHelper;
use Illuminate\Database\Eloquent\Model;

/**
 * Thin Eloquent mapper for attendance.sessions — HTTP Gate/Policy binding only.
 * No mass assignment; Application remains write authority.
 */
class AttendanceSessionRecord extends Model
{
    protected $table;

    /** @var list<string> */
    protected $fillable = [];

    public $timestamps = false;

    public function __construct(array $attributes = [])
    {
        $this->table = SchemaHelper::qualified('attendance', 'sessions');
        parent::__construct($attributes);
    }

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'session_date' => 'date',
        ];
    }
}
