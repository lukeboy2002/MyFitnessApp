<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('weekly_goal_sessions')->nullable()->after('weekly_goal_minutes');
            $table->unsignedSmallInteger('default_rest_seconds')->nullable()->after('weekly_goal_sessions');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['weekly_goal_sessions', 'default_rest_seconds']);
        });
    }
};
