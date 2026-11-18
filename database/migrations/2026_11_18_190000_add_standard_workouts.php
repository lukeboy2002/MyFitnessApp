<?php

use App\Models\Workout;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kant-en-klare workouts: user_id = null is een standaardworkout (door de admin gemaakt, voor iedereen zichtbaar).
 * Overnemen maakt een eigen kopie; source_workout_id onthoudt van welke standaardworkout die komt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workouts', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreignIdFor(Workout::class, 'source_workout_id')->nullable()->after('user_id')->constrained('workouts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workouts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_workout_id');
        });

        // Standaardworkouts hebben geen eigenaar; die kunnen niet terug in een kolom die verplicht is
        DB::table('workouts')->whereNull('user_id')->delete();

        Schema::table('workouts', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
