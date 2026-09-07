<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(SchemaHelper::qualified('students', 'students'), function (Blueprint $table): void {
            $table->foreignId('school_id')
                ->nullable()
                ->after('id')
                ->constrained(SchemaHelper::qualified('organization', 'schools'))
                ->restrictOnDelete();

            $table->index('school_id');
        });

        Schema::create(SchemaHelper::qualified('security', 'security_audit_logs'), function (Blueprint $table): void {
            $table->id();
            $table->string('event_id', 64);
            $table->timestampTz('occurred_at');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_type', 50)->default('user');
            $table->string('action', 100);
            $table->string('target_type', 50)->nullable();
            $table->string('target_id', 100)->nullable();
            $table->unsignedBigInteger('school_id')->nullable();
            $table->string('result', 50);
            $table->string('correlation_id', 100)->nullable();
            $table->json('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index('event_id');
            $table->index('occurred_at');
            $table->index('actor_id');
            $table->index('school_id');
            $table->index('correlation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SchemaHelper::qualified('security', 'security_audit_logs'));

        Schema::table(SchemaHelper::qualified('students', 'students'), function (Blueprint $table): void {
            $table->dropConstrainedForeignId('school_id');
        });
    }
};
