<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Pushmelding bij een uitnodiging of gedeelde workout van een maatje (Profiel > Meldingen, standaard aan) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('push_buddies')->default(true)->after('push_inactive');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('push_buddies');
        });
    }
};
