<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Database\SchemaHelper;
use Illuminate\Database\Eloquent\Model;

class ExamRecord extends Model
{
    protected $table;

    /** @var list<string> */
    protected $fillable = [];

    public function __construct(array $attributes = [])
    {
        $this->table = SchemaHelper::qualified('exams', 'exams');
        parent::__construct($attributes);
    }

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }
}
