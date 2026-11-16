<?php

use App\Models\Exercise;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Apparaatinstelling per gebruiker per oefening, bijv. "stoel 4, rugleuning 2" */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercise_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Exercise::class)->constrained()->cascadeOnDelete();
            $table->string('body', 200);
            $table->timestamps();

            $table->unique(['user_id', 'exercise_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_settings');
    }
};
