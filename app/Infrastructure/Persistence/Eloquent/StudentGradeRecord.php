<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Database\SchemaHelper;
use Illuminate\Database\Eloquent\Model;

class StudentGradeRecord extends Model
{
    protected $table;

    /** @var list<string> */
    protected $fillable = [];

    public function __construct(array $attributes = [])
    {
        $this->table = SchemaHelper::qualified('exams', 'student_grades');
        parent::__construct($attributes);
    }

    protected function casts(): array
    {
        return [
            'is_absent' => 'boolean',
            'is_current' => 'boolean',
            'status' => 'integer',
            'entered_at' => 'datetime',
            'finalized_at' => 'datetime',
            'score' => 'decimal:2',
            'max_score' => 'decimal:2',
        ];
    }
}
