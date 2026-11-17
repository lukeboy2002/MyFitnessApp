<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Superset: superset_next = deze oefening is gekoppeld aan de volgende (een paar).
 * Na een set van de eerste geen rust, pas na de set van de tweede.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['workout_exercises', 'session_exercises'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->boolean('superset_next')->default(false)->after('position');
            });
        }
    }

    public function down(): void
    {
        foreach (['workout_exercises', 'session_exercises'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('superset_next');
            });
        }
    }
};
