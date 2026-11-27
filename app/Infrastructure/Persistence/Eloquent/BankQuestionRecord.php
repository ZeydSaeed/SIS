<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Database\SchemaHelper;
use Illuminate\Database\Eloquent\Model;

/**
 * Thin Eloquent mapper for bank.questions — HTTP Gate/Policy binding only.
 * No mass assignment; Application remains read authority.
 */
class BankQuestionRecord extends Model
{
    protected $table;

    /** @var list<string> */
    protected $fillable = [];

    public $timestamps = false;

    public function __construct(array $attributes = [])
    {
        $this->table = SchemaHelper::qualified('bank', 'questions');
        parent::__construct($attributes);
    }
}
