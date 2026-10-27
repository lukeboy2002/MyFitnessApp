<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->after('weekly_goal_minutes');
            $table->string('gender', 10)->nullable()->after('birth_date');
            $table->unsignedSmallInteger('height_cm')->nullable()->after('gender');
            $table->decimal('target_weight_kg', 5, 1)->nullable()->after('height_cm');
            $table->string('goal', 20)->nullable()->after('target_weight_kg');
            $table->string('experience', 20)->nullable()->after('goal');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['birth_date', 'gender', 'height_cm', 'target_weight_kg', 'goal', 'experience']);
        });
    }
};
