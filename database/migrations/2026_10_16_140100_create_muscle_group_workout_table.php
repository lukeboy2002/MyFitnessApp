<?php

use App\Models\MuscleGroup;
use App\Models\Workout;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Welke spiergroepen een workout traint.
     */
    public function up(): void
    {
        Schema::create('muscle_group_workout', function (Blueprint $table) {
            $table->foreignIdFor(Workout::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(MuscleGroup::class)->constrained()->cascadeOnDelete();

            $table->primary(['workout_id', 'muscle_group_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('muscle_group_workout');
    }
};
