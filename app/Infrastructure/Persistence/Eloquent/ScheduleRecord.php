<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Database\SchemaHelper;
use Illuminate\Database\Eloquent\Model;

/**
 * Thin Eloquent mapper for timetable.schedules — HTTP Gate/Policy binding only.
 * No mass assignment; Application remains write authority.
 */
class ScheduleRecord extends Model
{
    protected $table;

    /** @var list<string> */
    protected $fillable = [];

    public $timestamps = false;

    public function __construct(array $attributes = [])
    {
        $this->table = SchemaHelper::qualified('timetable', 'schedules');
        parent::__construct($attributes);
    }

    protected function casts(): array
    {
        return [
            'lifecycle_status' => 'integer',
            'day_of_week' => 'integer',
        ];
    }
}
