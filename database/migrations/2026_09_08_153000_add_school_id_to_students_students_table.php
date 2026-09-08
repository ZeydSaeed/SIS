<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = SchemaHelper::qualified('students', 'students');

        if (! Schema::hasColumn($table, 'school_id')) {
            Schema::table($table, function (Blueprint $table): void {
                $table->foreignId('school_id')
                    ->nullable()
                    ->after('id')
                    ->constrained(SchemaHelper::qualified('organization', 'schools'))
                    ->restrictOnDelete();

                $table->index('school_id');
            });
        }
    }

    public function down(): void
    {
        $table = SchemaHelper::qualified('students', 'students');

        if (Schema::hasColumn($table, 'school_id')) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropForeign(['school_id']);
                $table->dropIndex(['school_id']);
                $table->dropColumn('school_id');
            });
        }
    }
};
