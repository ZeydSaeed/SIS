<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Database\SchemaHelper;
use Illuminate\Database\Eloquent\Model;

class ApplicationRecord extends Model
{
    protected $table;

    /** @var list<string> */
    protected $fillable = [];

    public function __construct(array $attributes = [])
    {
        $this->table = SchemaHelper::qualified('admission', 'applications');
        parent::__construct($attributes);
    }

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'gender' => 'integer',
            'grade_level_id' => 'integer',
            'birth_date' => 'date',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }
}
