<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Database\SchemaHelper;
use Illuminate\Database\Eloquent\Model;

class ApplicationDocumentRecord extends Model
{
    public $timestamps = false;

    protected $table;

    /** @var list<string> */
    protected $fillable = [];

    public function __construct(array $attributes = [])
    {
        $this->table = SchemaHelper::qualified('admission', 'application_documents');
        parent::__construct($attributes);
    }

    protected function casts(): array
    {
        return [
            'document_type' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
