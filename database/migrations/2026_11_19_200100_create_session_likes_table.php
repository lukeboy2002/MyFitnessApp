<?php

use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Duimen van vrienden op een gedeelde training */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(WorkoutSession::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['workout_session_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_likes');
    }
};
