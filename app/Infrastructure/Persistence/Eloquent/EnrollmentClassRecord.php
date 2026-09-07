<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Database\SchemaHelper;
use Illuminate\Database\Eloquent\Model;

class EnrollmentClassRecord extends Model
{
    protected $table;

    protected $fillable = [
        'code',
        'name',
        'capacity',
        'status',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = SchemaHelper::qualified('enrollment', 'classes');
        parent::__construct($attributes);
    }
}
