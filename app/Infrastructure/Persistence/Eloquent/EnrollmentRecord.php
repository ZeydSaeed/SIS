<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Database\SchemaHelper;
use Illuminate\Database\Eloquent\Model;

class EnrollmentRecord extends Model
{
    protected $table;

    protected $fillable = [
        'student_id',
        'academic_year_id',
        'school_id',
        'class_id',
        'section_id',
        'specialization_id',
        'enrollment_number',
        'status',
        'effective_from',
        'effective_to',
        'enrolled_by',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = SchemaHelper::qualified('enrollment', 'enrollments');
        parent::__construct($attributes);
    }

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'status' => 'integer',
        ];
    }
}
