<?php

use App\Models\DataImport;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Importeren uit een andere app (Hevy, Strong, FitNotes). Elke import onthoudt welke trainingen hij maakte,
 * zodat je hem in één keer ongedaan kunt maken, en hoe je de oefeningen hebt gekoppeld (voor een volgende keer).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('app', 20);                                 // hevy, strong, fitnotes
            $table->string('filename');
            $table->unsignedInteger('sessions_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);       // stonden er al in of zonder gekoppelde oefening
            $table->json('exercise_map')->nullable();                   // naam in de andere app => exercise_id (0 = overgeslagen)
            $table->timestamps();
        });

        Schema::table('workout_sessions', function (Blueprint $table) {
            $table->foreignIdFor(DataImport::class)->nullable()->after('external_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workout_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignIdFor(DataImport::class);
        });

        Schema::dropIfExists('data_imports');
    }
};
