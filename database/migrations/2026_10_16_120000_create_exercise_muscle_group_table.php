<?php

use App\Models\Exercise;
use App\Models\MuscleGroup;
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
        Schema::create('exercise_muscle_group', function (Blueprint $table) {
            $table->foreignIdFor(Exercise::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(MuscleGroup::class)->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false); // één primaire spiergroep per oefening

            $table->primary(['exercise_id', 'muscle_group_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exercise_muscle_group');
    }
};
