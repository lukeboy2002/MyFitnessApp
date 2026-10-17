<?php

use App\Models\SessionExercise;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kracht, lichaamsgewicht en tijd: één rij per set, met het vinkje.
     */
    public function up(): void
    {
        Schema::create('set_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(SessionExercise::class)->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('set_number');

            // Voorinvulling uit de doelen
            $table->unsignedSmallInteger('target_reps')->nullable();
            $table->decimal('target_weight', 6, 2)->nullable();
            $table->unsignedInteger('target_duration_seconds')->nullable();

            // Wat je echt deed
            $table->unsignedSmallInteger('reps')->nullable();
            $table->decimal('weight', 6, 2)->nullable();          // lichaamsgewicht: extra (negatief = assisted)
            $table->unsignedInteger('duration_seconds')->nullable();

            $table->boolean('is_completed')->default(false);
            $table->dateTime('completed_at')->nullable();
            $table->unsignedTinyInteger('rpe')->nullable();        // optioneel: zwaarte 1–10
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('set_logs');
    }
};
