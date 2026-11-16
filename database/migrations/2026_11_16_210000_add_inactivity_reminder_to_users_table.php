<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Niet-actief-melding: aan/uit (Profiel > Meldingen, standaard aan) en wanneer de laatste is verstuurd,
 * zodat je er per periode zonder training maar één krijgt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('push_inactive')->default(true)->after('push_weekly');
            $table->timestamp('inactive_reminded_at')->nullable()->after('push_inactive');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['push_inactive', 'inactive_reminded_at']);
        });
    }
};
