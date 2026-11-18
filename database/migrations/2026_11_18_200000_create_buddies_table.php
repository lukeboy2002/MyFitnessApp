<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maatjes: user_id nodigt buddy_id uit; accepted_at gevuld = maatjes (in beide richtingen).
 * Maatjes kunnen elkaar workouts sturen; ze zien elkaars trainingen niet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buddies', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class, 'buddy_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'buddy_id']);
            $table->index(['buddy_id', 'accepted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buddies');
    }
};
