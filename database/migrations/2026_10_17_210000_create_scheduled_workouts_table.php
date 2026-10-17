<?php

use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSession;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_workouts', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Workout::class)->constrained()->cascadeOnDelete();
            $table->date('scheduled_on');
            $table->foreignIdFor(WorkoutSession::class)->nullable()->constrained()->nullOnDelete(); // ingevuld zodra uitgevoerd
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'scheduled_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_workouts');
    }
};
