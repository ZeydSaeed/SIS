<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Database\SchemaHelper;
use Illuminate\Database\Eloquent\Model;

class EnrollmentRecord extends Model
{
    protected $table;

    /** @var list<string> */
    protected $fillable = [];

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
