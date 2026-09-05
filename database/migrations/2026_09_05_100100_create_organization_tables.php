<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(SchemaHelper::qualified('organization', 'ministries'), function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->smallInteger('status')->default(1);
            $table->timestamps();

            $table->index('status');
        });

        Schema::create(SchemaHelper::qualified('organization', 'directorates'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('ministry_id')
                ->constrained(SchemaHelper::qualified('organization', 'ministries'))
                ->restrictOnDelete();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('region', 100)->nullable();
            $table->smallInteger('status')->default(1);
            $table->timestamps();

            $table->index('ministry_id');
        });

        Schema::create(SchemaHelper::qualified('organization', 'schools'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('directorate_id')
                ->constrained(SchemaHelper::qualified('organization', 'directorates'))
                ->restrictOnDelete();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->smallInteger('school_type');
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->smallInteger('status')->default(1);
            $table->timestamps();

            $table->index(['directorate_id', 'status']);
        });

        Schema::create(SchemaHelper::qualified('organization', 'branches'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')
                ->constrained(SchemaHelper::qualified('organization', 'schools'))
                ->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->text('address')->nullable();
            $table->smallInteger('status')->default(1);
            $table->timestamps();

            $table->unique(['school_id', 'code']);
            $table->index('school_id');
        });

        Schema::create(SchemaHelper::qualified('organization', 'departments'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')
                ->constrained(SchemaHelper::qualified('organization', 'schools'))
                ->restrictOnDelete();
            $table->foreignId('branch_id')
                ->nullable()
                ->constrained(SchemaHelper::qualified('organization', 'branches'))
                ->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->smallInteger('department_type');
            $table->smallInteger('status')->default(1);
            $table->timestamps();

            $table->index('school_id');
            $table->index('branch_id');
        });

        Schema::create(SchemaHelper::qualified('organization', 'rooms'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')
                ->constrained(SchemaHelper::qualified('organization', 'branches'))
                ->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->smallInteger('capacity')->nullable();
            $table->smallInteger('room_type');
            $table->smallInteger('status')->default(1);
            $table->timestamps();

            $table->unique(['branch_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SchemaHelper::qualified('organization', 'rooms'));
        Schema::dropIfExists(SchemaHelper::qualified('organization', 'departments'));
        Schema::dropIfExists(SchemaHelper::qualified('organization', 'branches'));
        Schema::dropIfExists(SchemaHelper::qualified('organization', 'schools'));
        Schema::dropIfExists(SchemaHelper::qualified('organization', 'directorates'));
        Schema::dropIfExists(SchemaHelper::qualified('organization', 'ministries'));
    }
};
