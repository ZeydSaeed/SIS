<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Database\SchemaHelper;
use Illuminate\Database\Eloquent\Model;

/**
 * Thin Eloquent mapper for timetable.schedule_exceptions — HTTP Gate/Policy binding only.
 * No mass assignment; Application remains write authority.
 */
class ScheduleExceptionRecord extends Model
{
    protected $table;

    /** @var list<string> */
    protected $fillable = [];

    public $timestamps = false;

    public function __construct(array $attributes = [])
    {
        $this->table = SchemaHelper::qualified('timetable', 'schedule_exceptions');
        parent::__construct($attributes);
    }
}
