<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Training delen met vrienden: shared_at = zichtbaar voor vrienden (leeg = privé).
 * shared_summary = wat vrienden zien (duur, sets, volume, afstand, records), vastgelegd bij het delen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workout_sessions', function (Blueprint $table) {
            $table->timestamp('shared_at')->nullable()->after('notes');
            $table->json('shared_summary')->nullable()->after('shared_at');
            $table->index(['user_id', 'shared_at']);
        });
    }

    public function down(): void
    {
        Schema::table('workout_sessions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'shared_at']);
            $table->dropColumn(['shared_at', 'shared_summary']);
        });
    }
};
