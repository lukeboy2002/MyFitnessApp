<?php

use App\Models\Exercise;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSession;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Een oefening binnen een training. De doelen zijn een kopie van de workout,
     * zodat de historie klopt als je de workout later wijzigt.
     */
    public function up(): void
    {
        Schema::create('session_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(WorkoutSession::class)->constrained()->cascadeOnDelete();
            // Verwijderen van een oefening met historie blokkeert de app zelf (archiveren kan wel)
            $table->foreignIdFor(Exercise::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(WorkoutExercise::class)->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('position')->default(0);

            $table->unsignedTinyInteger('target_sets')->nullable();
            $table->unsignedSmallInteger('target_reps')->nullable();
            $table->decimal('target_weight', 6, 2)->nullable();
            $table->unsignedSmallInteger('rest_seconds')->nullable();
            $table->unsignedInteger('target_duration_seconds')->nullable();
            $table->string('target_metric', 20)->nullable();
            $table->decimal('target_metric_value', 5, 1)->nullable();

            $table->dateTime('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['exercise_id', 'workout_session_id']); // voortgang per oefening
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_exercises');
    }
};
