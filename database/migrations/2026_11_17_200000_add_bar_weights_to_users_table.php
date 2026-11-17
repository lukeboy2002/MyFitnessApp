<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Schijvencalculator: eigen gewicht van de stangen, bijv. {"bar": 15}; leeg = standaard (20 / 10 / 25 kg) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('bar_weights')->nullable()->after('default_rest_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('bar_weights');
        });
    }
};
