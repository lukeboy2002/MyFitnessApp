<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_heart_rate')->nullable()->after('experience');
            $table->unsignedTinyInteger('resting_heart_rate')->nullable()->after('max_heart_rate');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['max_heart_rate', 'resting_heart_rate']);
        });
    }
};
