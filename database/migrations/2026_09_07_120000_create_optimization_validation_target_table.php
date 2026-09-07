<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Isolated table for Phase 2B real ANALYZE validation — NOT production data.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement('CREATE SCHEMA IF NOT EXISTS intelligence');

        Schema::create(SchemaHelper::qualified('intelligence', 'optimization_validation_target'), function (Blueprint $table) {
            $table->id();
            $table->string('label', 100);
            $table->unsignedInteger('payload')->default(0);
            $table->timestamp('created_at')->useCurrent();
        });

        if (app()->runningUnitTests()) {
            return;
        }

        $this->seedDeterministicRows();
    }

    public function down(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        Schema::dropIfExists(SchemaHelper::qualified('intelligence', 'optimization_validation_target'));
    }

    private function seedDeterministicRows(): void
    {
        $qualified = SchemaHelper::qualified('intelligence', 'optimization_validation_target');
        $existing = (int) DB::table($qualified)->count();
        if ($existing >= 500) {
            return;
        }

        $rows = [];
        for ($i = 0; $i < 500; $i++) {
            $rows[] = [
                'label' => 'validation-row-'.$i,
                'payload' => $i,
            ];
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table($qualified)->insert($chunk);
        }

        DB::statement('ANALYZE '.$qualified);
    }
};
