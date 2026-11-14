<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Per soort pushmelding aan/uit: rust voorbij en berichten van de beheerder (standaard aan) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('push_rest')->default(true)->after('ban_reason');
            $table->boolean('push_admin')->default(true)->after('push_rest');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['push_rest', 'push_admin']);
        });
    }
};
