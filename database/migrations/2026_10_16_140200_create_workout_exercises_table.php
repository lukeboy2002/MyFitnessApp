<?php

use App\Models\Exercise;
use App\Models\Workout;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * De oefeningen in een workout, met hun doelen.
     * Eigen id, zodat dezelfde oefening twee keer in een workout kan.
     */
    public function up(): void
    {
        Schema::create('workout_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Workout::class)->constrained()->cascadeOnDelete();
            // Verwijderen van een oefening die in een workout zit, blokkeert de app zelf (archiveren kan wel)
            $table->foreignIdFor(Exercise::class)->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);

            // Kracht / lichaamsgewicht / tijd
            $table->unsignedTinyInteger('target_sets')->nullable();
            $table->unsignedSmallInteger('target_reps')->nullable();
            $table->decimal('target_weight', 6, 2)->nullable();   // bij lichaamsgewicht: extra gewicht (negatief = assisted)
            $table->unsignedSmallInteger('rest_seconds')->nullable();

            // Tijd per set (type tijd) of totale tijd (cardio)
            $table->unsignedInteger('target_duration_seconds')->nullable();

            // Cardio: naast de tijd één instelling (App\Enums\CardioMetric) met een waarde
            $table->string('target_metric', 20)->nullable();
            $table->decimal('target_metric_value', 5, 1)->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['workout_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workout_exercises');
    }
};
