<?php

use App\Models\User;
use App\Models\Workout;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Eén gestarte training, met timer. workout_id = null: losse oefening / leeg gestart.
     */
    public function up(): void
    {
        Schema::create('workout_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Workout::class)->nullable()->constrained()->nullOnDelete();
            $table->string('name');                               // kopie van de workoutnaam
            $table->string('color', 7)->nullable();
            $table->string('status', 20)->default('active');      // App\Enums\SessionStatus
            $table->dateTime('started_at');
            $table->dateTime('paused_at')->nullable();             // gezet zolang hij gepauzeerd is
            $table->unsignedInteger('paused_seconds')->default(0); // totale pauzetijd
            $table->dateTime('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable(); // ended_at − started_at − pauzes
            $table->string('source', 20)->default('app');           // later: import uit gezondheidsapp
            $table->string('external_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'started_at']);
            $table->unique(['user_id', 'external_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workout_sessions');
    }
};
