<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Database\SchemaHelper;
use Illuminate\Database\Eloquent\Model;

class StudentRecord extends Model
{
    protected $table;

    protected $fillable = [
        'school_id',
        'public_id',
        'student_code',
        'national_id',
        'first_name',
        'middle_name',
        'father_name',
        'grandfather_name',
        'great_grandfather_name',
        'last_name',
        'mother_name',
        'maternal_father_name',
        'maternal_grandfather_name',
        'full_name',
        'guardian_triple_name',
        'gender',
        'birth_date',
        'mawalid_date',
        'birth_place',
        'nationality',
        'governorate',
        'neighborhood',
        'locality',
        'house_number',
        'registration_place',
        'religion',
        'previous_school_name',
        'transfer_document_number',
        'transfer_document_date',
        'school_start_date',
        'admitted_class_name',
        'notes',
        'mobile',
        'guardian_mobile',
        'email',
        'school_name',
        'department_name',
        'specialization_name',
        'stage_name',
        'section_name',
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
            'mawalid_date' => 'date',
            'transfer_document_date' => 'date',
            'school_start_date' => 'date',
            'transfer_document_number' => 'integer',
            'status' => 'integer',
            'gender' => 'integer',
            'religion' => 'integer',
        ];
    }
}
