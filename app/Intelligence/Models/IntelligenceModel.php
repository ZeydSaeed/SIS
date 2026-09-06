<?php

namespace App\Intelligence\Models;

use App\Database\SchemaHelper;
use Illuminate\Database\Eloquent\Model;

abstract class IntelligenceModel extends Model
{
    protected static function intelligenceTable(string $table): string
    {
        return SchemaHelper::qualified('intelligence', $table);
    }
}
