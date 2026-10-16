<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->nullable()->constrained()->cascadeOnDelete(); // null = standaardlijst
            $table->string('name');
            $table->string('slug');
            $table->string('type', 20)->index();       // App\Enums\ExerciseType
            $table->string('equipment', 30)->nullable(); // App\Enums\Equipment
            $table->text('description')->nullable();
            $table->json('instructions')->nullable();  // stappen van de uitvoering
            $table->json('tips')->nullable();
            $table->string('image_path')->nullable();

            // Standaarddoel, wordt voorgevuld als je de oefening aan een workout toevoegt
            $table->unsignedTinyInteger('default_sets')->nullable();
            $table->unsignedSmallInteger('default_reps')->nullable();
            $table->decimal('default_weight', 6, 2)->nullable();
            $table->unsignedInteger('default_duration_seconds')->nullable();
            $table->unsignedTinyInteger('default_intensity')->nullable();

            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};
