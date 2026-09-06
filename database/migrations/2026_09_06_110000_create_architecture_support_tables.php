<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(SchemaHelper::qualified('audit', 'outbox_messages'), function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 255);
            $table->json('payload');
            $table->string('correlation_id', 64)->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampTz('processed_at')->nullable();
            $table->smallInteger('attempts')->default(0);
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['processed_at', 'created_at']);
            $table->index('correlation_id');
        });

        Schema::create(SchemaHelper::qualified('audit', 'idempotency_keys'), function (Blueprint $table) {
            $table->string('key', 64);
            $table->string('command_name', 255);
            $table->json('response_payload');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('expires_at');

            $table->primary(['key', 'command_name']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SchemaHelper::qualified('audit', 'idempotency_keys'));
        Schema::dropIfExists(SchemaHelper::qualified('audit', 'outbox_messages'));
    }
};
