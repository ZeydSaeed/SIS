<?php

use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(SchemaHelper::qualified('students', 'students'), function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique()->nullable();
            $table->string('student_code', 50)->unique();
            $table->string('national_id', 20)->nullable()->unique();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('full_name');
            $table->smallInteger('gender');
            $table->date('birth_date');
            $table->string('birth_place')->nullable();
            $table->string('nationality', 50)->nullable();
            $table->string('photo_storage_key', 500)->nullable();
            $table->smallInteger('status')->default(1);
            $table->timestamps();

            $table->index('status');
        });

        Schema::create(SchemaHelper::qualified('students', 'student_contacts'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')
                ->constrained(SchemaHelper::qualified('students', 'students'))
                ->restrictOnDelete();
            $table->smallInteger('contact_type');
            $table->string('value');
            $table->boolean('is_primary')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['student_id', 'contact_type']);
        });

        Schema::create(SchemaHelper::qualified('students', 'student_addresses'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')
                ->constrained(SchemaHelper::qualified('students', 'students'))
                ->restrictOnDelete();
            $table->smallInteger('address_type');
            $table->text('address_line');
            $table->string('city', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->index('student_id');
            $table->index(['student_id', 'is_current']);
        });

        Schema::create(SchemaHelper::qualified('guardians', 'guardians'), function (Blueprint $table) {
            $table->id();
            $table->string('national_id', 20)->nullable()->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('full_name');
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('occupation', 100)->nullable();
            $table->timestamps();

            $table->index('phone');
        });

        Schema::create(SchemaHelper::qualified('guardians', 'student_guardians'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')
                ->constrained(SchemaHelper::qualified('students', 'students'))
                ->restrictOnDelete();
            $table->foreignId('guardian_id')
                ->constrained(SchemaHelper::qualified('guardians', 'guardians'))
                ->restrictOnDelete();
            $table->smallInteger('relationship_type');
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_emergency_contact')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['student_id', 'guardian_id']);
            $table->index('guardian_id');
        });

        Schema::create(SchemaHelper::qualified('guardians', 'guardian_addresses'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_id')
                ->constrained(SchemaHelper::qualified('guardians', 'guardians'))
                ->restrictOnDelete();
            $table->text('address_line');
            $table->string('city', 100)->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->index('guardian_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(SchemaHelper::qualified('guardians', 'guardian_addresses'));
        Schema::dropIfExists(SchemaHelper::qualified('guardians', 'student_guardians'));
        Schema::dropIfExists(SchemaHelper::qualified('guardians', 'guardians'));
        Schema::dropIfExists(SchemaHelper::qualified('students', 'student_addresses'));
        Schema::dropIfExists(SchemaHelper::qualified('students', 'student_contacts'));
        Schema::dropIfExists(SchemaHelper::qualified('students', 'students'));
    }
};
