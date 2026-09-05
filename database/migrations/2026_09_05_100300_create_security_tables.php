<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(SchemaHelper::qualified('security', 'roles'), function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create(SchemaHelper::qualified('security', 'permissions'), function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 100)->unique();
            $table->string('name');
            $table->string('module', 50);
            $table->timestamp('created_at')->useCurrent();

            $table->index('module');
        });

        Schema::create(SchemaHelper::qualified('security', 'role_permissions'), function (Blueprint $table) {
            $table->unsignedSmallInteger('role_id');
            $table->unsignedSmallInteger('permission_id');

            $table->primary(['role_id', 'permission_id']);
            $table->foreign('role_id')
                ->references('id')
                ->on(SchemaHelper::qualified('security', 'roles'))
                ->restrictOnDelete();
            $table->foreign('permission_id')
                ->references('id')
                ->on(SchemaHelper::qualified('security', 'permissions'))
                ->restrictOnDelete();
        });

        Schema::create(SchemaHelper::qualified('security', 'user_roles'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->unsignedSmallInteger('role_id');
            $table->foreignId('school_id')
                ->nullable()
                ->constrained(SchemaHelper::qualified('organization', 'schools'))
                ->restrictOnDelete();
            $table->foreignId('directorate_id')
                ->nullable()
                ->constrained(SchemaHelper::qualified('organization', 'directorates'))
                ->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('role_id')
                ->references('id')
                ->on(SchemaHelper::qualified('security', 'roles'))
                ->restrictOnDelete();
            $table->index('user_id');
            $table->index('school_id');
            $table->index(['user_id', 'role_id']);
        });

        Schema::create(SchemaHelper::qualified('security', 'scopes'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('scope_type', 50);
            $table->unsignedBigInteger('scope_id');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'scope_type', 'scope_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SchemaHelper::qualified('security', 'scopes'));
        Schema::dropIfExists(SchemaHelper::qualified('security', 'user_roles'));
        Schema::dropIfExists(SchemaHelper::qualified('security', 'role_permissions'));
        Schema::dropIfExists(SchemaHelper::qualified('security', 'permissions'));
        Schema::dropIfExists(SchemaHelper::qualified('security', 'roles'));
    }
};
