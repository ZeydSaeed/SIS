<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Database\SchemaHelper;
use Illuminate\Database\Eloquent\Model;

class ApplicationPeriodRecord extends Model
{
    public $timestamps = false;

    protected $table;

    /** @var list<string> */
    protected $fillable = [];

    public function __construct(array $attributes = [])
    {
        $this->table = SchemaHelper::qualified('admission', 'application_periods');
        parent::__construct($attributes);
    }

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'max_applications' => 'integer',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
