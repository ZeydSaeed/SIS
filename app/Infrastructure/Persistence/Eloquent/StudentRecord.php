<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Database\SchemaHelper;
use Illuminate\Database\Eloquent\Model;

class StudentRecord extends Model
{
    protected $table;

    protected $fillable = [
        'public_id',
        'student_code',
        'national_id',
        'first_name',
        'middle_name',
        'last_name',
        'full_name',
        'gender',
        'birth_date',
        'birth_place',
        'nationality',
        'photo_storage_key',
        'status',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = SchemaHelper::qualified('students', 'students');
        parent::__construct($attributes);
    }

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'status' => 'integer',
            'gender' => 'integer',
        ];
    }
}
