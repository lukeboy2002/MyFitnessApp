<?php

use App\Models\User;
use App\Models\Workout;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gedeelde workouts: sender stuurt workout_id naar recipient. Openstaand zolang accepted_at en declined_at leeg zijn.
 * Overnemen maakt op dat moment een kopie (copy_workout_id) van de workout zoals hij dan is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shared_workouts', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Workout::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class, 'sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignIdFor(User::class, 'recipient_id')->constrained('users')->cascadeOnDelete();
            $table->string('message', 300)->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->foreignIdFor(Workout::class, 'copy_workout_id')->nullable()->constrained('workouts')->nullOnDelete();
            $table->timestamps();

            $table->index(['recipient_id', 'accepted_at', 'declined_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_workouts');
    }
};
