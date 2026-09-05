<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        SchemaHelper::createSchemas();
    }

    public function down(): void
    {
        SchemaHelper::dropSchemas();
    }
};
