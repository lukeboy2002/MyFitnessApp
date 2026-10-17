<?php

use App\Models\SessionExercise;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cardio: de gegevens van het apparaat, één rij per cardio-oefening.
     */
    public function up(): void
    {
        Schema::create('cardio_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(SessionExercise::class)->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('distance_m')->nullable();
            $table->string('metric', 20)->nullable();             // App\Enums\CardioMetric
            $table->decimal('metric_value', 5, 1)->nullable();
            $table->unsignedSmallInteger('avg_watts')->nullable();
            $table->decimal('mets', 4, 1)->nullable();
            $table->unsignedSmallInteger('active_kcal')->nullable();  // ACT
            $table->unsignedSmallInteger('total_kcal')->nullable();   // TTL
            $table->unsignedSmallInteger('avg_heart_rate')->nullable();
            $table->unsignedSmallInteger('cadence')->nullable();      // spm / rpm
            $table->decimal('incline', 4, 1)->nullable();
            $table->unsignedSmallInteger('avg_act_hr')->nullable();   // gem. actieve kcal per uur (zonder gekoppeld horloge)
            $table->unsignedInteger('rotations')->nullable();         // stairmaster, crosstrainer
            $table->unsignedSmallInteger('floors')->nullable();       // stairmaster
            $table->unsignedSmallInteger('stroke_rate')->nullable();  // roeien, ski
            $table->unsignedSmallInteger('pace_seconds')->nullable(); // roeien, ski, loopband
            $table->json('extra_metrics')->nullable();                // alles wat een apparaat nog meer geeft
            $table->string('source', 20)->default('manual');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cardio_logs');
    }
};
