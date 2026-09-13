<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Database\SchemaHelper;
use Illuminate\Database\Eloquent\Model;

/** Thin Eloquent mapper for vocational.workshops — HTTP Gate/Policy binding only. */
class WorkshopRecord extends Model
{
    protected $table;

    /** @var list<string> */
    protected $fillable = [];

    public $timestamps = false;

    public function __construct(array $attributes = [])
    {
        $this->table = SchemaHelper::qualified('vocational', 'workshops');
        parent::__construct($attributes);
    }
}
